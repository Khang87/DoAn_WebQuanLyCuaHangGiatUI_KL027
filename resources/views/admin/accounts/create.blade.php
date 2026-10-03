@extends('layouts.app')

@section('title', 'Thêm tài khoản - Sky Laundry')
@section('page-title', 'Thêm tài khoản')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Thông tin tài khoản mới</h5>
            <a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('accounts.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-12">
                    <div class="alert alert-info mb-0">Mỗi tài khoản phải liên kết với đúng một hồ sơ nhân viên hoặc khách hàng đã có.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" placeholder="Email đăng nhập (tối đa 100 ký tự)" maxlength="100" required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Số điện thoại</label>
                    <input type="text" class="form-control @error('phone') is-invalid @enderror" name="phone" value="{{ old('phone') }}" placeholder="0901234567" maxlength="15">
                    @error('phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" placeholder="Nhập mật khẩu" required>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Xác nhận mật khẩu <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" name="password_confirmation" placeholder="Xác nhận mật khẩu" required>
                </div>
                <div class="col-md-6">
                    <x-admin.role-select
                        name="role"
                        :selected="old('role', 'nhan-vien')"
                    />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="NhanVienID">Hồ sơ nhân viên</label>
                    <select class="form-select @error('NhanVienID') is-invalid @enderror" id="NhanVienID" name="NhanVienID">
                        <option value="">Không chọn nhân viên</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->NhanVienID }}" @selected(old('NhanVienID') == $employee->NhanVienID)>{{ $employee->HoTen }} · {{ $employee->SoDienThoai }}</option>
                        @endforeach
                    </select>
                    @error('NhanVienID')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="KhachHangID">Hồ sơ khách hàng</label>
                    <select class="form-select @error('KhachHangID') is-invalid @enderror" id="KhachHangID" name="KhachHangID">
                        <option value="">Không chọn khách hàng</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->KhachHangID }}" @selected(old('KhachHangID') == $customer->KhachHangID)>{{ $customer->HoTen }} · {{ $customer->SoDienThoai }}</option>
                        @endforeach
                    </select>
                    @error('KhachHangID')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-outline-secondary">Làm mới</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Tạo tài khoản
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
