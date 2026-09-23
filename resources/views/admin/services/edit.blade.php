@extends('layouts.app')

@section('title', 'Chỉnh Sửa Dịch Vụ - Giặt Ủi Pro')
@section('page-title', 'Chỉnh Sửa Dịch Vụ')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">{{ $service->name }}</h5>
            <a href="{{ route('services.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('services.update', $service->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Tên dịch vụ</label>
                    <input type="text" class="form-control" name="name" value="{{ $service->name }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Loại dịch vụ</label>
                    <select class="form-select" name="type">
                        <option value="wash" selected>Giặt</option>
                        <option value="dry_clean">Giặt khô</option>
                        <option value="iron">Ủi</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Giá cơ bản</label>
                    <input type="number" class="form-control" name="price" value="{{ $service->price }}" min="0" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Đơn vị tính</label>
                    <input type="text" class="form-control" name="unit" value="{{ $service->unit }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trạng thái</label>
                    <select class="form-select" name="status">
                        <option value="active" @selected($service->status === 'active')>Đang hoạt động</option>
                        <option value="inactive" @selected($service->status === 'inactive')>Tạm ngừng</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Mô tả</label>
                    <textarea class="form-control" name="description" rows="4">{{ $service->description }}</textarea>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('services.index') }}" class="btn btn-outline-secondary">Hủy</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Cập nhật
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
