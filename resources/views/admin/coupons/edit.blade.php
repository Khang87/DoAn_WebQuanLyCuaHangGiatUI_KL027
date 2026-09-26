@extends('layouts.app')

@section('title', 'Chỉnh sửa mã giảm giá - Sky Laundry')
@section('page-title', 'Chỉnh sửa mã giảm giá')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Chỉnh sửa mã giảm giá</h5>
            <a href="{{ route('coupons.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('coupons.update', $coupon->id) }}" method="POST">
            @csrf @method('PUT')
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Chương trình</label>
                    <select class="form-select" name="promotion_id">
                        <option value="">-- Chọn chương trình --</option>
                        @foreach($promotions as $promotion)
                        <option value="{{ $promotion->id }}" {{ $coupon->promotion_id === $promotion->id ? 'selected' : '' }}>{{ $promotion->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mã coupon <span class="text-danger ms-1">*</span></label>
                    <input type="text" class="form-control" name="code" value="{{ old('code', $coupon->code) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Loại giảm giá</label>
                    <select class="form-select" name="discount_type">
                        <option value="percent" {{ $coupon->discount_type === 'percent' ? 'selected' : '' }}>Phần trăm</option>
                        <option value="fixed" {{ $coupon->discount_type === 'fixed' ? 'selected' : '' }}>Cố định</option>
                        <option value="free_shipping" {{ $coupon->discount_type === 'free_shipping' ? 'selected' : '' }}>Miễn phí ship</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Giá trị</label>
                    <input type="number" class="form-control" name="discount_value" value="{{ old('discount_value', (float) $coupon->discount_value) }}" min="0">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Số lần tối đa</label>
                    <input type="number" class="form-control" name="max_uses" value="{{ old('max_uses', $coupon->max_uses) }}" min="1">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Hết hạn</label>
                    <input type="date" class="form-control" name="expires_at" value="{{ old('expires_at', $coupon->expires_at) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trạng thái</label>
                    <x-admin.status-select
                        name="status"
                        :options="\App\Enums\RecordStatus::options()"
                        :selected="$coupon->status"
                        class="form-select @error('status') is-invalid @enderror"
                    />
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-outline-secondary">Làm mới</button>
                <button type="submit" class="btn btn-primary">Cập nhật</button>
            </div>
        </form>
    </div>
</div>
@endsection
