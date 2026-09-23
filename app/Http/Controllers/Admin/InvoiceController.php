<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index()
    {
        return view('admin.invoices.index', ['invoices' => Invoice::latest()->get()]);
    }

    public function create()
    {
        return view('admin.invoices.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['order_id' => 'nullable|exists:orders,id', 'total' => 'required|numeric|min:0', 'status' => 'nullable|string', 'notes' => 'nullable|string']);
        $data['code'] = 'HD' . str_pad((string) ((Invoice::max('id') ?? 0) + 1), 3, '0', STR_PAD_LEFT);
        Invoice::create($data);
        return redirect()->route('invoices.index')->with('success', 'Hóa đơn đã được tạo thành công.');
    }

    public function show($id)
    {
        return view('admin.invoices.show', ['invoice' => Invoice::findOrFail($id)]);
    }

    public function edit($id)
    {
        return view('admin.invoices.edit', ['invoice' => Invoice::findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        Invoice::findOrFail($id)->update($request->validate(['order_id' => 'nullable|exists:orders,id', 'total' => 'required|numeric|min:0', 'status' => 'nullable|string', 'notes' => 'nullable|string']));
        return redirect()->route('invoices.index')->with('success', 'Hóa đơn đã được cập nhật.');
    }

    public function destroy($id)
    {
        Invoice::findOrFail($id)->delete();
        return redirect()->route('invoices.index')->with('success', 'Hóa đơn đã được xóa.');
    }
}
