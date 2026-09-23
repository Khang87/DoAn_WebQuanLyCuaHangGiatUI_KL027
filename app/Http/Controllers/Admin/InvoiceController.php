<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index()
    {
        return view('admin.invoices.index');
    }

    public function create()
    {
        return view('admin.invoices.create');
    }

    public function store(Request $request)
    {
        return redirect()->route('invoices.index')->with('success', 'Hóa đơn đã được tạo thành công.');
    }

    public function show($id)
    {
        return view('admin.invoices.show', compact('id'));
    }

    public function edit($id)
    {
        return view('admin.invoices.edit', compact('id'));
    }

    public function update(Request $request, $id)
    {
        return redirect()->route('invoices.index')->with('success', 'Hóa đơn đã được cập nhật.');
    }

    public function destroy($id)
    {
        return redirect()->route('invoices.index')->with('success', 'Hóa đơn đã được xóa.');
    }
}
