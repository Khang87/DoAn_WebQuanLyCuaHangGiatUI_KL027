@extends('layouts.app')

@section('title', 'Thêm khuyến mãi - Giặt Ủi Pro')
@section('page-title', 'Thêm khuyến mãi')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Thông tin chương trình</h5>
            <a href="{{ route('promotions.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('promotions.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Tên chương trình <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" placeholder="VD: Giảm 20% đơn đầu" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mã khuyến mãi <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('code') is-invalid @enderror" name="code" value="{{ old('code') }}" placeholder="VD: PROMO20" required>
                    @error('code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mức giảm <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('discount') is-invalid @enderror" name="discount" value="{{ old('discount') }}" placeholder="10% hoặc 50,000 VNĐ" required>
                    <div class="form-text">Nhập mô tả chiết khấu, ví dụ: 20% hoặc 50,000 VNĐ</div>
                    @error('discount')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ngày hết hạn <span class="text-danger">*</span></label>
                    <input type="date" class="form-control @error('expires_at') is-invalid @enderror" name="expires_at" value="{{ old('expires_at') }}" required>
                    @error('expires_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trạng thái</label>
                    <select class="form-select @error('status') is-invalid @enderror" name="status">
                        <option value="active" @selected(old('status', 'active') === 'active')>Đang chạy</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>Tắt</option>
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-outline-secondary">Làm mới</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Lưu khuyến mãi
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
