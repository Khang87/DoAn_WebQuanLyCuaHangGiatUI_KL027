@extends('layouts.app')

@section('title', 'Chỉnh sửa khuyến mãi - Sky Laundry')
@section('page-title', 'Chỉnh sửa khuyến mãi')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">{{ $promotion->name }}</h5>
            <a href="{{ route('promotions.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('promotions.update', $promotion->id) }}" method="POST">
            @csrf @method('PUT')
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Tên chương trình <span class="text-danger ms-1">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $promotion->name) }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mã khuyến mãi <span class="text-danger ms-1">*</span></label>
                    <input type="text" class="form-control @error('code') is-invalid @enderror" name="code" value="{{ old('code', $promotion->code) }}" required>
                    @error('code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mức giảm <span class="text-danger ms-1">*</span></label>
                    <input type="text" class="form-control @error('discount') is-invalid @enderror" name="discount" value="{{ old('discount', $promotion->discount) }}" placeholder="10% hoặc 50,000 VNĐ" required>
                    <div class="form-text">Nhập mô tả chiết khấu, ví dụ: 20% hoặc 50,000 VNĐ</div>
                    @error('discount')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Số lượt phát ra</label>
                    <input type="number" class="form-control @error('quantity') is-invalid @enderror" name="quantity" value="{{ old('quantity', $promotion->quantity) }}" min="1" placeholder="Để trống = không giới hạn">
                    <div class="form-text">Số lần áp dụng tối đa. Để trống = không giới hạn.</div>
                    @error('quantity')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ngày bắt đầu</label>
                    <input type="date" class="form-control @error('start_date') is-invalid @enderror" name="start_date" value="{{ old('start_date', $promotion->start_date?->format('Y-m-d')) }}">
                    @error('start_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ngày hết hạn <span class="text-danger ms-1">*</span></label>
                    <input type="date" class="form-control @error('expires_at') is-invalid @enderror" name="expires_at" value="{{ old('expires_at', $promotion->expires_at?->format('Y-m-d')) }}" required>
                    @error('expires_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trạng thái</label>
                    <select class="form-select @error('status') is-invalid @enderror" name="status">
                        @foreach($statuses ?? \App\Enums\RecordStatus::options() as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $promotion->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="conditions[first_order_only]" id="first_order_only" value="1" {{ old('conditions.first_order_only', $promotion->conditions['first_order_only'] ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="first_order_only">
                            Chỉ áp dụng cho đơn đầu tiên của khách
                        </label>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('promotions.index') }}" class="btn btn-outline-secondary">Hủy</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Lưu thay đổi
                </button>
            </div>
        </form>
    </div>
</div>
@endsection