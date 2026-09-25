@extends('layouts.app')

@section('title', 'Chỉnh sửa tài khoản - Sky Laundry')
@section('page-title', 'Chỉnh sửa tài khoản')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Chỉnh sửa tài khoản</h5>
            <a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('accounts.update', $account->id) }}" method="POST">
            @csrf @method('PUT')
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Họ tên <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $account->name) }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email', $account->email) }}" required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Số điện thoại</label>
                    <input type="text" class="form-control @error('phone') is-invalid @enderror" name="phone" value="{{ old('phone', $account->phone) }}" placeholder="0901234567">
                    @error('phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Vai trò <span class="text-danger">*</span></label>
                    <select class="form-select @error('role') is-invalid @enderror" name="role" required>
                        <option value="admin" {{ $account->role === 'admin' ? 'selected' : '' }}>Quản trị viên</option>
                        <option value="staff" {{ $account->role === 'staff' ? 'selected' : '' }}>Nhân viên</option>
                        <option value="customer" {{ $account->role === 'customer' ? 'selected' : '' }}>Khách hàng</option>
                    </select>
                    @error('role')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mật khẩu mới</label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" placeholder="Nhập mật khẩu mới (bỏ trống nếu không đổi)">
                    <small class="form-text text-muted">Bỏ trống nếu không thay đổi mật khẩu.</small>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Xác nhận mật khẩu</label>
                    <input type="password" class="form-control" name="password_confirmation" placeholder="Xác nhận mật khẩu mới">
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-outline-secondary">Làm mới</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Cập nhật tài khoản
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
