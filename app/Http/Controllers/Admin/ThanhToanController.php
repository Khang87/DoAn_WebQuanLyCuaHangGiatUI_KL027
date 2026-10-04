<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\SettledOrderException;
use App\Http\Controllers\Concerns\RejectsSettledRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LuuThanhToanRequest;
use App\Models\DonHang;
use App\Models\HoaDon;
use App\Services\PaymentService;
use App\Support\FriendlyError;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ThanhToanController extends Controller
{
    use RejectsSettledRecords;

    public function __construct(
        private PaymentService $paymentService,
    ) {}

    private function canOverrideSettled(): bool
    {
        return auth()->user()?->canPermission('payments.edit_paid') ?? false;
    }

    private function canDeleteSettled(): bool
    {
        return auth()->user()?->canPermission('payments.delete_paid') ?? false;
    }

    private function denyIfPaidAndNotOwner(Request $request, $payment, string $action): ?Response
    {
        if ($payment->isLocked() && ! auth()->user()?->isOwner()) {
            return $this->denySettled(
                $request,
                'Khoản thu này đã thanh toán. Bạn không có quyền '.$action.'.',
                $action === 'xóa' ? route('payments.index') : route('payments.show', $payment)
            );
        }

        return null;
    }

    public function index(Request $request)
    {
        $payments = $this->paymentService->getAll([
            'search' => $request->input('search'),
            'order_id' => $request->input('order_id'),
            'invoice_id' => $request->input('invoice_id'),
            'method' => $request->input('method'),
            'status' => $request->input('status'),
            'sort' => $request->input('sort'),
        ]);

        $methods = [
            'cash' => 'Tiền mặt',
            'bank_transfer' => 'Chuyển khoản / QR',
        ];

        return view('admin.payments.index', compact('payments', 'methods'));
    }

    public function create(Request $request)
    {
        $orderId = $request->query('order_id');
        $preselectedOrder = null;
        $preselectedInvoice = null;
        $preselectedAmount = null;

        if ($orderId !== null) {
            $preselectedOrder = DonHang::with(['khachHang', 'chiTietDonHangs.dichVu', 'hoaDons'])
                ->find($orderId);

            if (! $preselectedOrder) {
                return redirect()->route('orders.index')->with('error', 'Không tìm thấy đơn hàng cần thanh toán.');
            }

            if ($preselectedOrder->isLocked()) {
                return redirect()->route('orders.show', $preselectedOrder)
                    ->with('error', 'Đơn hàng này đã được thanh toán.');
            }

            if ($preselectedOrder->TrangThai === OrderStatus::Cancelled->value) {
                return redirect()->route('orders.index')->with('error', 'Không thể thanh toán đơn hàng đã hủy.');
            }

            $preselectedInvoice = $preselectedOrder->hoaDons->first();
            $orderTotal = $preselectedOrder->hoaDons->first()?->ThanhTien ?? $preselectedOrder->ThanhTien;
            $paidTotal = $preselectedOrder->thanhToans()
                ->where('TrangThai', PaymentStatus::Paid->value)
                ->sum('SoTien');
            $preselectedAmount = max(0, (float) $orderTotal - (float) $paidTotal);
        }

        $orders = DonHang::with('khachHang')
            ->whereNotIn('TrangThai', [OrderStatus::Cancelled->value, OrderStatus::Paid->value])
            ->orderBy('NgayTao', 'desc')
            ->get();
        $invoices = HoaDon::with('donHang.khachHang')
            ->where('TrangThai', '!=', InvoiceStatus::Paid->value)
            ->when($preselectedOrder, fn ($query) => $query->where('DonHangID', $preselectedOrder->getKey()))
            ->orderBy('NgayLap', 'desc')
            ->get();

        $invoiceId = $request->query('invoice_id');
        if (! $preselectedOrder) {
            $preselectedInvoice = $invoiceId ? HoaDon::find($invoiceId) : null;
        }

        return view('admin.payments.create', compact('orders', 'invoices', 'preselectedOrder', 'preselectedInvoice', 'preselectedAmount'));
    }

    public function store(LuuThanhToanRequest $request)
    {
        try {
            $data = $request->validated();
            $data['status'] = PaymentStatus::Paid->value;
            $data['paid_at'] = now();
            $payment = $this->paymentService->create($data);

            return redirect()->route('payments.show', $payment)->with('success', 'Thanh toán đã được tạo thành công.');
        } catch (\Exception $e) {
            return redirect()->route('payments.create')->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id)
    {
        $payment = $this->paymentService->find($id);

        if (! $payment) {
            abort(404);
        }

        return view('admin.payments.show', compact('payment'));
    }

    public function edit(Request $request, int $id)
    {
        $payment = $this->paymentService->find($id);

        if (! $payment) {
            abort(404);
        }

        // Kiểm tra khoản thu đã thanh toán - nhân viên/quản lý không được sửa
        if ($denied = $this->denyIfPaidAndNotOwner($request, $payment, 'sửa')) {
            return $denied;
        }

        if ($payment->isLocked() && ! $this->canOverrideSettled()) {
            return $this->denySettled(
                $request,
                'Khoản thu này đã ghi nhận tiền nên chỉ có thể xem.',
                route('payments.show', $payment)
            );
        }

        $orders = DonHang::with('khachHang')->where('TrangThai', '!=', 'Đã hủy')->orderBy('NgayTao', 'desc')->get();
        $invoices = HoaDon::with('donHang.khachHang')->where('TrangThai', '!=', InvoiceStatus::Paid->value)->orderBy('NgayLap', 'desc')->get();

        return view('admin.payments.edit', compact('payment', 'orders', 'invoices'));
    }

    public function update(LuuThanhToanRequest $request, int $id)
    {
        $payment = $this->paymentService->find($id);

        if (! $payment) {
            abort(404);
        }

        // Kiểm tra khoản thu đã thanh toán - nhân viên/quản lý không được sửa
        if ($denied = $this->denyIfPaidAndNotOwner($request, $payment, 'chỉnh sửa')) {
            return $denied;
        }

        $override = $this->canOverrideSettled();

        if ($payment->isLocked() && ! $override) {
            return $this->denySettled(
                $request,
                'Khoản thu này đã ghi nhận tiền nên không thể chỉnh sửa.',
                route('payments.show', $payment)
            );
        }

        try {
            $this->paymentService->update($payment, $request->validated(), $override);

            return redirect()->route('payments.index')->with('success', 'Thanh toán đã được cập nhật.');
        } catch (SettledOrderException $e) {
            return $this->rejectSettled($request, $e->getMessage(), route('payments.show', $payment));
        } catch (\Exception $e) {
            return redirect()->route('payments.edit', $payment)->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function destroy(Request $request, int $id)
    {
        $payment = $this->paymentService->find($id);

        if (! $payment) {
            abort(404);
        }

        // Kiểm tra khoản thu đã thanh toán - nhân viên/quản lý không được xóa
        if ($denied = $this->denyIfPaidAndNotOwner($request, $payment, 'xóa')) {
            return $denied;
        }

        $override = $this->canDeleteSettled();

        if ($payment->isLocked() && ! $override) {
            return $this->denySettled(
                $request,
                'Khoản thu này đã ghi nhận tiền nên không thể xóa.',
                route('payments.index')
            );
        }

        try {
            $this->paymentService->delete($payment, $override);

            return redirect()->route('payments.index')->with('success', 'Thanh toán đã được xóa.');
        } catch (SettledOrderException $e) {
            return $this->rejectSettled($request, $e->getMessage(), route('payments.index'));
        } catch (\Exception $e) {
            return redirect()->route('payments.index')->with('error', FriendlyError::message($e));
        }
    }
}
