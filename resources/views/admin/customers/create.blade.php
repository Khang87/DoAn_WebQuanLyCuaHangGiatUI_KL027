@extends('layouts.app')

@section('title', 'Thêm khách hàng - Sky Laundry')
@section('page-title', 'Thêm khách hàng')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Thông tin khách hàng</h5>
            <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('customers.store') }}" method="POST" id="customer-create-form">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Họ tên <span class="text-danger ms-1">*</span></label>
                    <input type="text" class="form-control @error('HoTen') is-invalid @enderror" name="HoTen" value="{{ old('HoTen') }}" placeholder="Nhập họ tên" required>
                    @error('HoTen')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email <span class="text-danger ms-1">*</span></label>
                    <input type="email" class="form-control @error('Email') is-invalid @enderror" name="Email" value="{{ old('Email') }}" placeholder="Nhập email" required>
                    @error('Email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Số điện thoại <span class="text-danger ms-1">*</span></label>
                    <input type="tel" class="form-control @error('SoDienThoai') is-invalid @enderror" name="SoDienThoai" value="{{ old('SoDienThoai') }}" placeholder="Nhập số điện thoại" pattern="0[0-9]{9,10}" maxlength="30" required>
                    @error('SoDienThoai')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text" id="customer-contact-help">Nhập số điện thoại gồm 10 hoặc 11 chữ số, bắt đầu bằng số 0.</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Địa chỉ</label>
                    <textarea class="form-control @error('DiaChi') is-invalid @enderror" name="DiaChi" rows="3" placeholder="Nhập địa chỉ"></textarea>
                    @error('DiaChi')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>Thêm mới
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
