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
                    <label class="form-label">Danh mục</label>
                    <select class="form-select @error('service_category_id') is-invalid @enderror" name="service_category_id">
                        <option value="">Không có</option>
                        @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('service_category_id', $service->service_category_id) == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('service_category_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tên dịch vụ</label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $service->name) }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Loại dịch vụ</label>
                    <select class="form-select @error('type') is-invalid @enderror" name="type">
                        <option value="wash" @selected(old('type', $service->type) === 'wash')>Giặt</option>
                        <option value="dry_clean" @selected(old('type', $service->type) === 'dry_clean')>Giặt khô</option>
                        <option value="iron" @selected(old('type', $service->type) === 'iron')>Ủi</option>
                        <option value="blanket" @selected(old('type', $service->type) === 'blanket')>Chăn mền</option>
                        <option value="shoes" @selected(old('type', $service->type) === 'shoes')>Giày dép</option>
                        <option value="express" @selected(old('type', $service->type) === 'express')>Giao nhanh</option>
                    </select>
                    @error('type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Thời gian xử lý (Giờ)</label>
                    <input type="number" class="form-control @error('processing_time') is-invalid @enderror" name="processing_time" value="{{ old('processing_time', $service->processing_time) }}" min="1" placeholder="24">
                    <div class="form-text">Nhập số giờ xử lý, ví dụ: 2 = 2 giờ, 24 = 1 ngày, 48 = 2 ngày</div>
                    @error('processing_time')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Icon FontAwesome</label>
                    <input type="text" class="form-control @error('icon') is-invalid @enderror" name="icon" value="{{ old('icon', $service->icon) }}" placeholder="fa-solid fa-washer">
                    @error('icon')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Giá cơ bản</label>
                    <input type="number" class="form-control @error('price') is-invalid @enderror" name="price" value="{{ old('price', $service->price) }}" min="0" required>
                    @error('price')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Đơn vị tính</label>
                    <select class="form-select @error('unit') is-invalid @enderror" name="unit">
                        <option value="kg" @selected(old('unit', $service->unit ?: 'kg') === 'kg')>kg</option>
                        <option value="món" @selected(old('unit', $service->unit) === 'món')>món</option>
                        <option value="combo" @selected(old('unit', $service->unit) === 'combo')>combo</option>
                        <option value="đôi" @selected(old('unit', $service->unit) === 'đôi')>đôi</option>
                        <option value="cái" @selected(old('unit', $service->unit) === 'cái')>cái</option>
                    </select>
                    @error('unit')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trạng thái</label>
                    <select class="form-select @error('status') is-invalid @enderror" name="status">
                        <option value="active" @selected(old('status', $service->status) === 'active')>Đang hoạt động</option>
                        <option value="inactive" @selected(old('status', $service->status) === 'inactive')>Tạm ngưng</option>
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Mô tả chi tiết</label>
                    <textarea class="form-control @error('description') is-invalid @enderror" name="description" rows="3" placeholder="Nhập mô tả chi tiết về dịch vụ...">{{ old('description', $service->description) }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
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
