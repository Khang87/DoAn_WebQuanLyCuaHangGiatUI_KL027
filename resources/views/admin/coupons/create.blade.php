@extends('layouts.app')

@section('title', 'Thêm mã giảm giá - Giặt Ủi Pro')
@section('page-title', 'Thêm mã giảm giá')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Mã giảm giá</h5>
            <a href="{{ route('coupons.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('coupons.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Chương trình khuyến mãi <span class="text-danger">*</span></label>
                    <select class="form-select @error('promotion_id') is-invalid @enderror" name="promotion_id" required>
                        <option value="">Chọn</option>
                        @foreach($promotions as $promotion)
                        <option value="{{ $promotion->id }}">{{ $promotion->name }} ({{ $promotion->code }})</option>
                        @endforeach
                    </select>
                    @error('promotion_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mã coupon <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('code') is-invalid @enderror" name="code" value="{{ old('code') }}" placeholder="vd: VIP20" required>
                    @error('code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Loại giảm giá <span class="text-danger">*</span></label>
                    <select class="form-select @error('discount_type') is-invalid @enderror" name="discount_type" required>
                        <option value="percent">Phần trăm (%)</option>
                        <option value="fixed">Số tiền cố định</option>
                        <option value="free_shipping">Miễn phí ship</option>
                    </select>
                    @error('discount_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Giá trị giảm giá <span class="text-danger">*</span></label>
                    <input type="number" class="form-control @error('discount_value') is-invalid @enderror" name="discount_value" value="{{ old('discount_value') }}" min="0" required>
                    @error('discount_value')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Số lần sử dụng tối đa</label>
                    <input type="number" class="form-control" name="max_uses" value="{{ old('max_uses') }}" min="1">
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
                    <select class="form-select" name="status">
                        <option value="active" selected>Hoạt động</option>
                        <option value="inactive">Tắt</option>
                    </select>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-outline-secondary">Làm mới</button>
                <button type="submit" class="btn btn-primary">Tạo</button>
            </div>
        </form>
    </div>
</div>
@endsection
