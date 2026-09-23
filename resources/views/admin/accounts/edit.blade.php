@extends('layouts.app')

@section('title', 'Sửa Tài Khoản - Giặt Ủi Pro')
@section('page-title', 'Cập Nhật Tài Khoản')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">{{ $account->name }}</h5>
            <a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('accounts.update', $account->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Họ tên</label>
                    <input type="text" class="form-control" name="name" value="{{ $account->name }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" class="form-control" name="email" value="{{ $account->email }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Mật khẩu mới (Để trống nếu giữ nguyên)</label>
                    <input type="password" class="form-control" name="password" minlength="6" placeholder="Nhập mật khẩu mới nếu muốn đổi">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Vai trò phân quyền</label>
                    <select class="form-select" name="role">
                        <option value="customer" @selected($account->role === 'customer')>Khách hàng (Customer)</option>
                        <option value="staff" @selected($account->role === 'staff')>Nhân viên (Staff)</option>
                        <option value="admin" @selected($account->role === 'admin')>Quản trị viên (Admin)</option>
                    </select>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary">Hủy</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Lưu thay đổi
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
