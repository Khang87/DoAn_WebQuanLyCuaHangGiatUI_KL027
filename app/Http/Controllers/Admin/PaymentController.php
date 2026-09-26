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

    public function index(Request $request)
    {
        $payments = $this->paymentService->getAll([
            'search' => $request->input('search'),
            'order_id' => $request->input('order_id'),
            'invoice_id' => $request->input('invoice_id'),
            'method' => $request->input('method'),
            'status' => $request->input('status'),
            'sort_by' => $request->input('sort_by'),
            'sort_order' => $request->input('sort_order'),
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

    public function edit(Request $request, int $id)
    {
        $payment = $this->paymentService->find($id);

        if (!$payment) {
            abort(404);
        }

        $invoice = $payment->invoice ?? $payment->order?->invoice;

        if ($invoice?->isPaid()) {
            return $this->rejectSettled(
                $request,
                'Hóa đơn '.$invoice->code.' đã thanh toán nên khoản thu này chỉ có thể xem.',
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

        try {
            $this->paymentService->update($payment, $request->validated());

            return redirect()->route('payments.index')->with('success', 'Thanh toán đã được cập nhật.');
        } catch (SettledOrderException $e) {
            return $this->rejectSettled($request, $e->getMessage(), route('payments.show', $payment));
        } catch (\Exception $e) {
            return redirect()->route('payments.edit', $payment)->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(Request $request, int $id)
    {
        $payment = $this->paymentService->find($id);

        if (!$payment) {
            abort(404);
        }

        try {
            $this->paymentService->delete($payment);

            return redirect()->route('payments.index')->with('success', 'Thanh toán đã được xóa.');
        } catch (SettledOrderException $e) {
            return $this->rejectSettled($request, $e->getMessage(), route('payments.index'));
        } catch (\Exception $e) {
            return redirect()->route('payments.index')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
}
