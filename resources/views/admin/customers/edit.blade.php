@extends('layouts.app')

@section('title', 'Chỉnh sửa khách hàng - Sky Laundry')
@section('page-title', 'Chỉnh sửa khách hàng')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Chỉnh sửa khách hàng</h5>
            <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('customers.update', $customer->getKey()) }}" method="POST">
            @csrf @method('PUT')
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Họ tên <span class="text-danger ms-1">*</span></label>
                    <input type="text" class="form-control @error('HoTen') is-invalid @enderror" name="HoTen" value="{{ old('HoTen', $customer->name) }}" required>
                    @error('HoTen')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control @error('Email') is-invalid @enderror" name="Email" value="{{ old('Email', $customer->email) }}">
                    @error('Email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Số điện thoại</label>
                    <input type="text" class="form-control @error('SoDienThoai') is-invalid @enderror" name="SoDienThoai" value="{{ old('SoDienThoai', $customer->phone) }}">
                    @error('SoDienThoai')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Điểm tích lũy</label>
                    <input type="number" class="form-control @error('DiemHienTai') is-invalid @enderror" name="DiemHienTai" value="{{ old('DiemHienTai', $customer->points) }}" min="0">
                    @error('DiemHienTai')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Địa chỉ</label>
                    <textarea class="form-control @error('DiaChi') is-invalid @enderror" name="DiaChi" rows="3">{{ old('DiaChi', $customer->address) }}</textarea>
                    @error('DiaChi')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-outline-secondary">Làm mới</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Cập nhật
                </button>
            </div>
        </form>
    </div>
</div>
@endsection