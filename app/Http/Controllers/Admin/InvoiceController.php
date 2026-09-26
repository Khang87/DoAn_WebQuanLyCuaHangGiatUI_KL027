<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Exceptions\SettledOrderException;
use App\Http\Controllers\Concerns\RejectsSettledRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InvoiceRequest;
use App\Models\Order;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    use RejectsSettledRecords;

    public function __construct(
        private InvoiceService $invoiceService,
    ) {}

    public function index(Request $request)
    {
        $invoices = $this->invoiceService->getAll([
            'search' => $request->input('search'),
            'order_id' => $request->input('order_id'),
            'status' => $request->input('status'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ]);

        $statuses = InvoiceStatus::options();

        return view('admin.invoices.index', compact('invoices', 'statuses'));
    }

    public function create(Request $request)
    {
        $orders = Order::where('status', '!=', 'cancelled')->orderBy('created_at', 'desc')->get();

        $orderId = $request->query('order_id');
        $preselectedOrder = $orderId ? Order::find($orderId) : null;

        return view('admin.invoices.create', compact('orders', 'preselectedOrder'));
    }

    public function store(InvoiceRequest $request)
    {
        try {
            $invoice = $this->invoiceService->create($request->validated());

            return redirect()->route('invoices.show', $invoice)->with('success', 'Hóa đơn đã được tạo thành công.');
        } catch (SettledOrderException $e) {
            return $this->rejectSettled($request, $e->getMessage(), route('invoices.index'));
        } catch (\Exception $e) {
            return redirect()->route('invoices.create')->with('error', 'Có lỗi xảy ra: '.$e->getMessage())->withInput();
        }
    }

    public function show(int|string $id)
    {
        $invoice = $this->invoiceService->findDetailed($id);

        if (! $invoice) {
            abort(404);
        }

        return view('admin.invoices.show', compact('invoice'));
    }

    public function edit(Request $request, int|string $id)
    {
        $invoice = $this->invoiceService->find($id);

        if (! $invoice) {
            abort(404);
        }

        if ($invoice->isPaid()) {
            return $this->rejectSettled(
                $request,
                'Hóa đơn '.$invoice->code.' đã thanh toán nên chỉ có thể xem.',
                route('invoices.show', $invoice)
            );
        }

        $orders = Order::where('status', '!=', 'cancelled')->orderBy('created_at', 'desc')->get();

        return view('admin.invoices.edit', compact('invoice', 'orders'));
    }

    public function update(InvoiceRequest $request, int|string $id)
    {
        $invoice = $this->invoiceService->find($id);

        if (! $invoice) {
            abort(404);
        }

        try {
            $this->invoiceService->update($invoice, $request->validated());

            return redirect()->route('invoices.index')->with('success', 'Hóa đơn đã được cập nhật.');
        } catch (SettledOrderException $e) {
            return $this->rejectSettled($request, $e->getMessage(), route('invoices.show', $invoice));
        } catch (\Exception $e) {
            return redirect()->route('invoices.edit', $invoice)->with('error', 'Có lỗi xảy ra: '.$e->getMessage())->withInput();
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

            return redirect()->route('invoices.index')->with('success', 'Hóa đơn đã được xóa.');
        } catch (SettledOrderException $e) {
            return $this->rejectSettled($request, $e->getMessage(), route('invoices.index'));
        } catch (\Exception $e) {
            return redirect()->route('invoices.index')->with('error', 'Có lỗi xảy ra: '.$e->getMessage());
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
            return $this->rejectSettled($request, $e->getMessage(), route('invoices.show', $invoice));
        } catch (\Exception $e) {
            return back()->with('error', 'Có lỗi xảy ra: '.$e->getMessage());
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
                $invoice->code,
                $invoice->invoice_date?->format('d/m/Y') ?? $invoice->created_at?->format('d/m/Y'),
                $invoice->order?->code,
                $invoice->order?->customer?->name,
                (float) $invoice->total_amount,
                (float) $invoice->discount_amount,
                (float) $invoice->delivery_fee,
                (float) $invoice->grand_total,
                $invoice->getStatusLabel(),
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

    public function exportExcel(int|string $id): StreamedResponse
    {
        $invoice = $this->invoiceService->findDetailed($id);

        if (! $invoice) {
            abort(404);
        }

        $fileName = 'Hoa_Don_'.$invoice->code.'_'.now()->format('Y_m_d').'.xlsx';

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Hóa đơn '.$invoice->code);

        $sheet->fromArray([
            ['SKY LAUNDRY'],
            ['Số 123 Đường Lê Lợi, Quận 1, TP. HCM'],
            ['Hotline: 0909.123.456'],
            [],
            ['HÓA ĐƠN DỊCH VỤ GIẶT ỦI'],
            ['Mã hóa đơn:', $invoice->code],
            ['Ngày lập:', $invoice->invoice_date?->format('d/m/Y') ?? now()->format('d/m/Y')],
            ['Mã đơn:', $invoice->order?->code],
            ['Khách hàng:', $invoice->order?->customer?->name],
            ['SĐT:', $invoice->order?->customer?->phone],
            ['Địa chỉ:', $invoice->order?->customer?->address],
            [],
        ], null, 'A1');

        $sheet->fromArray([
            ['STT', 'Tên Dịch Vụ / Loại Đồ', 'Đơn Vị Tính', 'Số Lượng', 'Đơn Giá (VNĐ)', 'Thành Tiền (VNĐ)'],
        ], null, 'A14');

        $row = 15;
        foreach ($invoice->order?->items ?? [] as $index => $item) {
            $sheet->fromArray([[
                $index + 1,
                $item->service?->name ?: ($item->item_name ?: '-'),
                $item->service?->unit ?: 'kg',
                $item->quantity ?? 0,
                (float) ($item->price ?? 0),
                (float) ($item->subtotal ?? 0),
            ]], null, 'A'.$row);
            $row++;
        }

        $sheet->fromArray([
            ['', '', '', '', 'Tạm tính:', (float) $invoice->total_amount],
            ['', '', '', '', 'Giảm giá:', (float) $invoice->discount_amount],
            ['', '', '', '', 'Phí giao hàng:', (float) $invoice->delivery_fee],
            ['', '', '', '', 'TỔNG CỘNG:', (float) $invoice->grand_total],
        ], null, 'A'.$row);

        $sheet->getStyle('A1:F13')->getFont()->setBold(true);
        $sheet->getStyle('E14:F14')->getFont()->setBold(true);
        $sheet->getStyle('E'.($row+3).':F'.($row+3))->getFont()->setBold(true)->setSize(14);
        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
