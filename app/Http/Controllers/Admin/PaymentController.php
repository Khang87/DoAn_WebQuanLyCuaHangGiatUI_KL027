<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentRequest;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
    ) {}

    public function index(Request $request)
    {
        $payments = $this->paymentService->getAll([
            'search' => $request->input('search'),
            'order_id' => $request->input('order_id'),
            'method' => $request->input('method'),
            'status' => $request->input('status'),
        ]);

        $orders = \App\Models\Order::orderBy('code')->get();
        $methods = ['cash' => 'Tiền mặt', 'bank_transfer' => 'Chuyển khoản', 'e_wallet' => 'Ví điện tử'];

        return view('admin.payments.index', compact('payments', 'orders', 'methods'));
    }

    public function create()
    {
        $orders = \App\Models\Order::orderBy('code')->get();
        $methods = ['cash' => 'Tiền mặt', 'bank_transfer' => 'Chuyển khoản', 'e_wallet' => 'Ví điện tử'];
        $statuses = ['pending' => 'Chưa thanh toán', 'partial' => 'Thanh toán một phần', 'paid' => 'Đã thanh toán'];

        return view('admin.payments.create', compact('orders', 'methods', 'statuses'));
    }

    public function store(PaymentRequest $request)
    {
        try {
            $this->paymentService->create($request->validated());

            return redirect()->route('payments.index')->with('success', 'Thanh toán đã được tạo.');
        } catch (\Exception $e) {
            return redirect()->route('payments.create')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
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

    public function edit(int $id)
    {
        $payment = $this->paymentService->find($id);

        if (!$payment) {
            abort(404);
        }

        $orders = \App\Models\Order::orderBy('code')->get();
        $methods = ['cash' => 'Tiền mặt', 'bank_transfer' => 'Chuyển khoản', 'e_wallet' => 'Ví điện tử'];
        $statuses = ['pending' => 'Chưa thanh toán', 'partial' => 'Thanh toán một phần', 'paid' => 'Đã thanh toán'];

        return view('admin.payments.edit', compact('payment', 'orders', 'methods', 'statuses'));
    }

    public function update(PaymentRequest $request, int $id)
    {
        $payment = $this->paymentService->find($id);

        if (!$payment) {
            abort(404);
        }

        try {
            $this->paymentService->update($payment, $request->validated());

            return redirect()->route('payments.index')->with('success', 'Thanh toán đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('payments.edit', $payment)->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(int $id)
    {
        $payment = $this->paymentService->find($id);

        if ($payment) {
            try {
                $this->paymentService->delete($payment);
            } catch (\Exception $e) {
                return redirect()->route('payments.index')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
            }
        }

        return redirect()->route('payments.index')->with('success', 'Thanh toán đã được xóa.');
    }
}
