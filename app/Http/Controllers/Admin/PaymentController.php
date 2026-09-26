<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\SettledOrderException;
use App\Http\Controllers\Concerns\RejectsSettledRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentRequest;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
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

    private function denyIfPaidAndNotOwner(Request $request, $payment, string $action): ?\Illuminate\Http\Response
    {
        if ($payment->isLocked() && ! auth()->user()?->isOwner()) {
            return $this->denySettled(
                $request,
                'Khoản thu này đã thanh toán. Bạn không có quyền ' . $action . '.',
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
            'momo' => 'Ví MoMo',
            'credit_card' => 'Thẻ ATM / Credit',
            'e_wallet' => 'Ví điện tử',
        ];

        return view('admin.payments.index', compact('payments', 'methods'));
    }

    public function create(Request $request)
    {
        $orders = Order::where('status', '!=', 'cancelled')->orderBy('created_at', 'desc')->get();
        $invoices = Invoice::where('status', '!=', 'paid')->orderBy('created_at', 'desc')->get();

        $orderId = $request->query('order_id');
        $invoiceId = $request->query('invoice_id');
        $preselectedOrder = $orderId ? Order::find($orderId) : null;
        $preselectedInvoice = $invoiceId ? Invoice::find($invoiceId) : null;

        return view('admin.payments.create', compact('orders', 'invoices', 'preselectedOrder', 'preselectedInvoice'));
    }

    public function store(PaymentRequest $request)
    {
        try {
            $payment = $this->paymentService->create($request->validated());

            return redirect()->route('payments.show', $payment)->with('success', 'Thanh toán đã được tạo thành công.');
        } catch (\Exception $e) {
            return redirect()->route('payments.create')->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id)
    {
        $payment = $this->paymentService->find($id);

        if (!$payment) {
            abort(404);
        }

        return view('admin.payments.show', compact('payment'));
    }

    public function edit(Request $request, int $id)
    {
        $payment = $this->paymentService->find($id);

        if (!$payment) {
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

        $orders = Order::where('status', '!=', 'cancelled')->orderBy('created_at', 'desc')->get();
        $invoices = Invoice::where('status', '!=', 'paid')->orderBy('created_at', 'desc')->get();

        return view('admin.payments.edit', compact('payment', 'orders', 'invoices'));
    }

    public function update(PaymentRequest $request, int $id)
    {
        $payment = $this->paymentService->find($id);

        if (!$payment) {
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
            return redirect()->route('payments.edit', $payment)->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }

    public function destroy(Request $request, int $id)
    {
        $payment = $this->paymentService->find($id);

        if (!$payment) {
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
            return redirect()->route('payments.index')->with('error', \App\Support\FriendlyError::message($e));
        }
    }
}
