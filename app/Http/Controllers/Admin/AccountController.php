<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    /**
     * Danh sách tài khoản hệ thống
     */
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        $accounts = $query->latest()->get();

        return view('admin.accounts.index', compact('accounts'));
    }

    /**
     * Form tạo tài khoản
     */
    public function create()
    {
        return view('admin.accounts.create');
    }

    /**
     * Lưu tài khoản mới
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'nullable|in:admin,staff,customer',
        ]);

        $data['role'] = $data['role'] ?? 'customer';
        $data['password'] = Hash::make($data['password']);

        User::create($data);

        return redirect()->route('accounts.index')->with('success', 'Tài khoản đã được tạo thành công với vai trò: ' . ($data['role'] === 'admin' ? 'Quản trị viên' : ($data['role'] === 'staff' ? 'Nhân viên' : 'Khách hàng')));
    }

    /**
     * Xem chi tiết tài khoản
     */
    public function show($id)
    {
        $account = User::findOrFail($id);
        return view('admin.accounts.show', compact('account'));
    }

    /**
     * Form chỉnh sửa tài khoản
     */
    public function edit($id)
    {
        $account = User::findOrFail($id);
        return view('admin.accounts.edit', compact('account'));
    }

    /**
     * Cập nhật tài khoản
     */
    public function update(Request $request, $id)
    {
        $account = User::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:6',
            'role' => 'required|in:admin,staff,customer',
        ]);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $account->update($data);

        return redirect()->route('accounts.index')->with('success', 'Tài khoản đã được cập nhật.');
    }

    /**
     * Xóa tài khoản
     */
    public function destroy($id)
    {
        abort_if((int) $id === (int) auth()->id(), 422, 'Không thể xóa tài khoản đang đăng nhập.');
        User::findOrFail($id)->delete();

        return redirect()->route('accounts.index')->with('success', 'Tài khoản đã được xóa.');
    }
}
