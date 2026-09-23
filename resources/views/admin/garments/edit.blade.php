@extends('layouts.app')

@section('title', 'Chỉnh Sửa Loại Đồ Giặt - Giặt Ủi Pro')
@section('page-title', 'Chỉnh Sửa Loại Đồ Giặt')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">{{ $garment->name }}</h5>
            <a href="{{ route('garments.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('garments.update', $garment->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Tên loại đồ giặt / sản phẩm</label>
                    <input type="text" class="form-control" name="name" value="{{ $garment->name }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Danh mục loại đồ</label>
                    <input type="text" class="form-control" name="category" value="{{ $garment->category }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Đơn giá dịch vụ (VNĐ)</label>
                    <input type="number" class="form-control" name="price" value="{{ $garment->price }}" min="0" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Trạng thái</label>
                    <select class="form-select" name="status">
                        <option value="active" @selected($garment->status === 'active')>Đang hoạt động</option>
                        <option value="inactive" @selected($garment->status === 'inactive')>Tạm ngưng</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Mô tả hiện trạng trước khi giặt</label>
                    <textarea class="form-control" name="condition_note" rows="4">{{ $garment->condition_note }}</textarea>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('garments.index') }}" class="btn btn-outline-secondary">Hủy</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Cập nhật
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
