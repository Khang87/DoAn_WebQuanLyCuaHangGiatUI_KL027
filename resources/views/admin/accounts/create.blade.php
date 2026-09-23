@extends('layouts.app')

@section('title', 'Thêm Tài Khoản - Giặt Ủi Pro')
@section('page-title', 'Thêm Tài Khoản Mới')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Thông tin tài khoản</h5>
            <a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('accounts.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Họ tên người dùng</label>
                    <input type="text" class="form-control" name="name" placeholder="Nhập họ tên đầy đủ" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Địa chỉ Email</label>
                    <input type="email" class="form-control" name="email" placeholder="example@domain.com" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Mật khẩu khởi tạo</label>
                    <input type="password" class="form-control" name="password" minlength="6" placeholder="Tối thiểu 6 ký tự" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Vai trò phân quyền (Mặc định: Khách hàng)</label>
                    <select class="form-select" name="role">
                        <option value="customer" selected>Khách hàng (Customer)</option>
                        <option value="staff">Nhân viên (Staff)</option>
                        <option value="admin">Quản trị viên (Admin)</option>
                    </select>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary">Hủy</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-person-plus me-1"></i>Tạo tài khoản
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
