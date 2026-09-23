@extends('layouts.app')

@section('title', 'Chỉnh sửa khuyến mãi - Giặt Ủi Pro')
@section('page-title', 'Chỉnh sửa khuyến mãi')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Chỉnh sửa khuyến mãi</h5>
            <a href="{{ route('promotions.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('promotions.update', $promotion->id) }}" method="POST">
            @csrf @method('PUT')
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Tên chương trình</label>
                    <input type="text" class="form-control" name="name" value="{{ old('name', $promotion->name) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mã khuyến mãi</label>
                    <input type="text" class="form-control" name="code" value="{{ old('code', $promotion->code) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mức giảm</label>
                    <input type="text" class="form-control" name="discount" value="{{ old('discount', $promotion->discount) }}" placeholder="10% hoặc 50,000 VNĐ" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ngày hết hạn</label>
                    <input type="date" class="form-control" name="expires_at" value="{{ old('expires_at', $promotion->expires_at) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trạng thái</label>
                    <select class="form-select" name="status">
                        <option value="active" {{ $promotion->status === 'active' ? 'selected' : '' }}>Hoạt động</option>
                        <option value="inactive" {{ $promotion->status === 'inactive' ? 'selected' : '' }}>Tắt</option>
                    </select>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('promotions.index') }}" class="btn btn-outline-secondary">Hủy</a>
                <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
            </div>
        </form>
    </div>
</div>
@endsection
