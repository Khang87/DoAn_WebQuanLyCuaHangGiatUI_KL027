<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\SettledOrderException;
use App\Http\Controllers\Concerns\RejectsSettledRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LuuHoaDonRequest;
use App\Models\DonHang;
use App\Models\HoaDon;
use App\Services\InvoiceService;
use App\Support\FriendlyError;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HoaDonController extends Controller
{
    use RejectsSettledRecords;

    public function __construct(
        private InvoiceService $invoiceService,
    ) {}

    public function index(Request $request)
    {
        $dateFilters = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        // Hóa đơn chỉ được "xuất" sau khi thu tiền, nên module Hóa đơn chỉ liệt kê
        // hóa đơn đã thanh toán. Hóa đơn chờ thu theo dõi và thu tại trang Đơn hàng.
        $invoices = $this->invoiceService->getAll([
            'search' => $request->input('search'),
            'order_id' => $request->input('order_id'),
            'status' => InvoiceStatus::Paid->value,
            'date_from' => $dateFilters['date_from'] ?? null,
            'date_to' => $dateFilters['date_to'] ?? null,
        ]);

        return view('admin.invoices.index', compact('invoices'));
    }

    public function create(Request $request)
    {
        $orders = DonHang::where('TrangThai', '!=', 'Đã hủy')->orderBy('NgayTao', 'desc')->get();

        $orderId = $request->query('order_id');
        $preselectedOrder = $orderId ? DonHang::find($orderId) : null;

        return view('admin.invoices.create', compact('orders', 'preselectedOrder'));
    }

    public function store(LuuHoaDonRequest $request)
    {
        try {
            $invoice = $this->invoiceService->create($request->validated());

            $order = DonHang::find($request->input('order_id'));
            if ($order) {
                return redirect()->route('orders.show', $order)->with('success', 'Hóa đơn đã được tạo thành công.');
            }

            return redirect()->route('invoices.index')
                ->with('success', 'Hóa đơn '.$invoice->MaHoaDon.' đã được lập và chờ thu tiền; thu xong mới hiển thị ở danh sách Hóa đơn.');
        } catch (SettledOrderException $e) {
            // Không tạo được hoá đơn thứ hai cho đơn đã quyết toán là quy tắc
            // kế toán, áp dụng với MỌI vai trò kể cả Chủ cửa hàng, nên vẫn
            // trả về redirect kèm thông báo thay vì 403.
            return $this->rejectSettled($request, $e->getMessage(), route('invoices.index'));
        } catch (\Exception $e) {
            $createParams = $request->filled('order_id') ? ['order_id' => $request->input('order_id')] : [];

            return redirect()->route('invoices.create', $createParams)->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function show(int|string $id)
    {
        $invoice = $this->invoiceService->findDetailed($id);

        if (! $invoice) {
            abort(404);
        }

        if (! $invoice->isPaid()) {
            return redirect()->route('invoices.index')
                ->with('error', 'Hóa đơn '.$invoice->MaHoaDon.' chưa thanh toán nên chưa hiển thị. Thu tiền xong hóa đơn mới xuất hiện ở danh sách.');
        }

        $totalPaid = (float) ($invoice->donHang?->thanhToans
            ->where('TrangThai', PaymentStatus::Paid->value)
            ->sum('SoTien') ?? 0);
        $balance = max(0, (float) $invoice->ThanhTien - $totalPaid);

        return view('admin.invoices.show', compact('invoice', 'totalPaid', 'balance'));
    }

    /**
     * Hóa đơn chờ thu không nằm trong danh sách Hóa đơn nên điểm quay lại hợp lý
     * là trang Đơn hàng của nó (nếu tài khoản này xem được đơn hàng).
     */
    private function returnUrlFor(HoaDon $invoice): string
    {
        if ($invoice->DonHangID && (auth()->user()?->canPermission('orders.view') ?? false)) {
            return route('orders.show', $invoice->DonHangID);
        }

        return route('invoices.index');
    }

    public function edit(Request $request, int|string $id)
    {
        $invoice = $this->invoiceService->find($id);

        if (! $invoice) {
            abort(404);
        }

        if ($invoice->isPaid()) {
            return $this->denySettled(
                $request,
                'Hóa đơn '.$invoice->MaHoaDon.' đã thanh toán nên chỉ có thể xem.',
                route('invoices.show', $invoice)
            );
        }

        $orders = DonHang::where('TrangThai', '!=', 'Đã hủy')->orderBy('NgayTao', 'desc')->get();
        $backUrl = $this->returnUrlFor($invoice);

        return view('admin.invoices.edit', compact('invoice', 'orders', 'backUrl'));
    }

    public function update(LuuHoaDonRequest $request, int|string $id)
    {
        $invoice = $this->invoiceService->find($id);

        if (! $invoice) {
            abort(404);
        }

        try {
            $this->invoiceService->update($invoice, $request->validated());

            $saved = $this->invoiceService->find($invoice->getKey()) ?? $invoice;

            return redirect()->to($this->returnUrlFor($saved))->with('success', 'Hóa đơn đã được cập nhật.');
        } catch (SettledOrderException $e) {
            return $this->denySettled($request, $e->getMessage(), route('invoices.show', $invoice));
        } catch (\Exception $e) {
            return redirect()->route('invoices.edit', $invoice)->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function destroy(Request $request, int|string $id)
    {
        $invoice = $this->invoiceService->find($id);

        if (! $invoice) {
            abort(404);
        }

        try {
            $this->invoiceService->delete($invoice);

            return redirect()->to($this->returnUrlFor($invoice))->with('success', 'Hóa đơn đã được xóa.');
        } catch (SettledOrderException $e) {
            return $this->denySettled($request, $e->getMessage(), route('invoices.index'));
        } catch (\Exception $e) {
            return redirect()->route('invoices.index')->with('error', FriendlyError::message($e));
        }
    }

    public function updateStatus(Request $request, int|string $id)
    {
        $invoice = $this->invoiceService->find($id);

        if (! $invoice) {
            abort(404);
        }

        $status = $request->input('status');

        try {
            $this->invoiceService->updateStatus($invoice, $status);

            return back()->with('success', 'Trạng thái hóa đơn đã được cập nhật.');
        } catch (SettledOrderException $e) {
            return $this->denySettled($request, $e->getMessage(), route('invoices.show', $invoice));
        } catch (\Exception $e) {
            return back()->with('error', FriendlyError::message($e));
        }
    }

    public function export(Request $request): StreamedResponse
    {
        $fileName = 'Danh_Sach_Hoa_Don_'.now()->format('Y_m_d').'.xlsx';
        $filters = $request->query();
        $invoices = $this->invoiceService->getAll($filters)->getCollection();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Danh sách hóa đơn');
        $sheet->fromArray([
            ['Mã hóa đơn', 'Ngày lập', 'Mã đơn hàng', 'Khách hàng', 'Tổng tiền', 'Giảm giá', 'Phí giao hàng', 'Tổng thanh toán', 'Trạng thái'],
        ], null, 'A1');

        $row = 2;
        foreach ($invoices as $invoice) {
            $sheet->fromArray([[
                $invoice->MaHoaDon,
                $invoice->NgayLap?->format('d/m/Y') ?? $invoice->NgayTao?->format('d/m/Y'),
                $invoice->donHang?->MaDonHang,
                $invoice->donHang?->khachHang?->HoTen,
                (float) $invoice->TongTien,
                (float) $invoice->GiamGia,
                (float) $invoice->PhiGiaoHang,
                (float) $invoice->ThanhTien,
                InvoiceStatus::labelFor($invoice->TrangThai),
            ]], null, 'A'.$row);
            $row++;
        }

        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->getStyle('E1:I'.max($row - 1, 1))->getNumberFormat()->setFormatCode('#,##0');
        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
