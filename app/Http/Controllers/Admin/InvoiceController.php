<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InvoiceRequest;
use App\Services\InvoiceService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService,
    ) {}

    public function index(Request $request)
    {
        $invoices = $this->invoiceService->getAll([
            'order_id' => $request->input('order_id'),
            'status' => $request->input('status'),
        ]);

        $orders = \App\Models\Order::orderBy('code')->get();
        $statuses = ['unpaid' => 'Chưa thanh toán', 'partial' => 'Một phần', 'paid' => 'Đã thanh toán'];

        return view('admin.invoices.index', compact('invoices', 'orders', 'statuses'));
    }

    public function create()
    {
        $orders = \App\Models\Order::orderBy('code')->get();
        $statuses = ['unpaid' => 'Chưa thanh toán', 'partial' => 'Một phần', 'paid' => 'Đã thanh toán'];

        return view('admin.invoices.create', compact('orders', 'statuses'));
    }

    public function store(InvoiceRequest $request)
    {
        try {
            $invoice = $this->invoiceService->create($request->validated());

            return redirect()->route('invoices.index')->with('success', 'Hóa đơn đã được tạo.');
        } catch (\Exception $e) {
            return redirect()->route('invoices.create')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function show(int $id)
    {
        $invoice = $this->invoiceService->find($id);

        if (!$invoice) {
            abort(404);
        }

        return view('admin.invoices.show', compact('invoice'));
    }

    public function edit(int $id)
    {
        $invoice = $this->invoiceService->find($id);

        if (!$invoice) {
            abort(404);
        }

        $orders = \App\Models\Order::orderBy('code')->get();
        $statuses = ['unpaid' => 'Chưa thanh toán', 'partial' => 'Một phần', 'paid' => 'Đã thanh toán'];

        return view('admin.invoices.edit', compact('invoice', 'orders', 'statuses'));
    }

    public function update(InvoiceRequest $request, int $id)
    {
        $invoice = $this->invoiceService->find($id);

        if (!$invoice) {
            abort(404);
        }

        try {
            $this->invoiceService->update($invoice, $request->validated());

            return redirect()->route('invoices.index')->with('success', 'Hóa đơn đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('invoices.edit', $invoice)->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(int $id)
    {
        $invoice = $this->invoiceService->find($id);

        if ($invoice) {
            try {
                $this->invoiceService->delete($invoice);
            } catch (\Exception $e) {
                return redirect()->route('invoices.index')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
            }
        }

        return redirect()->route('invoices.index')->with('success', 'Hóa đơn đã được xóa.');
    }
}
