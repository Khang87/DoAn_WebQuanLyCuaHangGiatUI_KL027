<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index()
    {
        return view('admin.accounts.index');
    }

    public function create()
    {
        return view('admin.accounts.create');
    }

    public function store(Request $request)
    {
        return redirect()->route('accounts.index')->with('success', 'Tài khoản đã được tạo thành công.');
    }

    public function show($id)
    {
        return view('admin.accounts.show', compact('id'));
    }

    public function edit($id)
    {
        return view('admin.accounts.edit', compact('id'));
    }

    public function update(Request $request, $id)
    {
        return redirect()->route('accounts.index')->with('success', 'Tài khoản đã được cập nhật.');
    }

    public function destroy($id)
    {
        return redirect()->route('accounts.index')->with('success', 'Tài khoản đã được xóa.');
    }
}
