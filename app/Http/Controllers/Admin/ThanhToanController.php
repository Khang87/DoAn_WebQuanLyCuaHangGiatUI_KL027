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
use App\Support\BankTransferReference;
use App\Support\FriendlyError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

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
            $preselectedOrder = DonHang::with(['khachHang', 'chiTietDonHangs.dichVu', 'hoaDons', 'booking'])
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
            $preselectedAmount = (float) ($preselectedOrder->hoaDons->first()?->ThanhTien ?? $preselectedOrder->ThanhTien);
        }

        $orders = DonHang::with([
            'khachHang',
            'hoaDons' => fn ($query) => $query->whereNotIn('TrangThai', [
                InvoiceStatus::Paid->value,
                InvoiceStatus::Cancelled->value,
            ]),
        ])
            ->whereNotIn('TrangThai', [OrderStatus::Cancelled->value, OrderStatus::Paid->value])
            ->orderBy('NgayTao', 'desc')
            ->get();
        $invoices = HoaDon::with('donHang.khachHang')
            ->whereNotIn('TrangThai', [InvoiceStatus::Paid->value, InvoiceStatus::Cancelled->value])
            ->when($preselectedOrder, fn ($query) => $query->where('DonHangID', $preselectedOrder->getKey()))
            ->orderBy('NgayLap', 'desc')
            ->get();

        $invoiceId = $request->query('invoice_id');
        if (! $preselectedOrder) {
            $preselectedInvoice = $invoiceId ? HoaDon::find($invoiceId) : null;
            if ($preselectedInvoice && InvoiceStatus::parse($preselectedInvoice->TrangThai) === InvoiceStatus::Cancelled) {
                return redirect()->route('payments.index')->with('error', 'Không thể tạo thanh toán cho hóa đơn đã hủy.');
            }
        } elseif ($preselectedInvoice && InvoiceStatus::parse($preselectedInvoice->TrangThai) === InvoiceStatus::Cancelled) {
            return redirect()->route('orders.show', $preselectedOrder)
                ->with('error', 'Không thể tạo thanh toán cho hóa đơn đã hủy.');
        }

        return view('admin.payments.create', compact('orders', 'invoices', 'preselectedOrder', 'preselectedInvoice', 'preselectedAmount'));
    }

    public function store(LuuThanhToanRequest $request)
    {
        if ($request->validated('method') !== 'cash') {
            throw ValidationException::withMessages([
                'method' => 'Chuyển khoản chỉ được ghi nhận khi ngân hàng xác nhận giao dịch.',
            ]);
        }

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

    public function startBankTransfer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:DonHang,DonHangID'],
            'invoice_id' => ['nullable', 'integer', 'exists:HoaDon,HoaDonID'],
        ]);

        $intent = DB::transaction(function () use ($data): array {
            $order = DonHang::query()->lockForUpdate()->findOrFail($data['order_id']);
            $invoice = $order->hoaDons()->lockForUpdate()->orderBy('HoaDonID')->first();
            if (! $invoice || InvoiceStatus::parse($invoice->TrangThai) !== InvoiceStatus::Unpaid) {
                throw ValidationException::withMessages(['order_id' => 'Đơn hàng cần có hóa đơn chưa thanh toán để tạo mã QR.']);
            }
            if (isset($data['invoice_id']) && (int) $data['invoice_id'] !== (int) $invoice->getKey()) {
                throw ValidationException::withMessages(['invoice_id' => 'Hóa đơn không thuộc lựa chọn thanh toán hiện tại.']);
            }
            if ($order->statusEnum() === OrderStatus::Cancelled || $order->isPaid()) {
                throw ValidationException::withMessages(['order_id' => 'Đơn hàng không còn nhận thanh toán.']);
            }

            $pending = $order->thanhToans()->where('PhuongThuc', 'Chuyển khoản')
                ->where('TrangThai', PaymentStatus::Pending->value)->lockForUpdate()->first();
            if ($pending) {
                if (round((float) $pending->SoTien, 2) !== round((float) $invoice->ThanhTien, 2)) {
                    $this->paymentService->update($pending, [
                        'order_id' => $order->getKey(), 'invoice_id' => $invoice->getKey(),
                        'amount' => $invoice->ThanhTien, 'method' => 'bank_transfer',
                        'status' => PaymentStatus::Pending->value,
                    ]);
                }
            } else {
                $this->paymentService->create([
                    'order_id' => $order->getKey(), 'invoice_id' => $invoice->getKey(),
                    'amount' => $invoice->ThanhTien, 'method' => 'bank_transfer',
                    'status' => PaymentStatus::Pending->value,
                ]);
            }

            return [
                'order_id' => (int) $order->getKey(),
                'amount' => (float) $invoice->ThanhTien,
                'reference' => BankTransferReference::forOrder((int) $order->getKey()),
            ];
        });

        return response()->json($intent)->header('Cache-Control', 'no-store, private');
    }

    public function bankTransferStatus(Request $request): JsonResponse
    {
        $data = $request->validate(['order_id' => ['required', 'integer', 'exists:DonHang,DonHangID']]);
        $order = DonHang::with('hoaDons')->findOrFail($data['order_id']);
        $payment = $order->thanhToans()->where('TrangThai', PaymentStatus::Paid->value)
            ->orderByDesc('ThoiGian')->orderByDesc('ThanhToanID')->first();
        $paid = $order->hoaDons->contains(fn ($invoice) => $invoice->isPaid());

        return response()->json([
            'paid' => $paid && $payment?->PhuongThuc === 'Chuyển khoản',
            'settled_by_other_method' => $paid && $payment && $payment->PhuongThuc !== 'Chuyển khoản',
            'transaction_code' => $paid ? $payment?->MaGiaoDich : null,
        ])->header('Cache-Control', 'no-store, private');
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

        $fullAmount = (float) ($payment->donHang?->hoaDons?->first()?->ThanhTien
            ?? $payment->donHang?->ThanhTien
            ?? $payment->SoTien);
        $orders = DonHang::with('khachHang')->where('TrangThai', '!=', 'Đã hủy')->orderBy('NgayTao', 'desc')->get();
        $invoices = HoaDon::with('donHang.khachHang')
            ->whereNotIn('TrangThai', [InvoiceStatus::Paid->value, InvoiceStatus::Cancelled->value])
            ->orderBy('NgayLap', 'desc')
            ->get();

        return view('admin.payments.edit', compact('payment', 'orders', 'invoices', 'fullAmount'));
    }

    public function update(LuuThanhToanRequest $request, int $id)
    {
        $payment = $this->paymentService->find($id);

        if (! $payment) {
            abort(404);
        }

        if ($request->validated('status') === PaymentStatus::Paid->value
            && ($request->validated('method') === 'bank_transfer'
                || ($payment->PhuongThuc === 'Chuyển khoản' && $payment->TrangThai === PaymentStatus::Pending->value))) {
            throw ValidationException::withMessages([
                'status' => 'Chuyển khoản chỉ được ghi nhận khi ngân hàng xác nhận giao dịch.',
            ]);
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
