@extends('layouts.app')

@section('title', 'Thêm Dịch Vụ - Giặt Ủi Pro')
@section('page-title', 'Thêm Dịch Vụ')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Thông tin dịch vụ</h5>
            <a href="{{ route('services.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('services.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Danh mục</label>
                    <select class="form-select" name="service_category_id">
                        <option value="">Không có</option>
                        @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tên dịch vụ</label>
                    <input type="text" class="form-control" name="name" placeholder="Nhập tên dịch vụ" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Loại dịch vụ</label>
                    <select class="form-select" name="type">
                        <option value="wash">Giặt</option>
                        <option value="dry_clean">Giặt khô</option>
                        <option value="iron">Ủi</option>
                        <option value="blanket">Chăn mền</option>
                        <option value="shoes">Giày dep</option>
                        <option value="express">Giao nhanh</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Thời gian xử lý (giờ)</label>
                    <input type="number" class="form-control @error('processing_time') is-invalid @enderror" name="processing_time" value="{{ old('processing_time') }}" min="0" placeholder="24">
                    @error('processing_time')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Icon FontAwesome</label>
                    <input type="text" class="form-control @error('icon') is-invalid @enderror" name="icon" value="{{ old('icon') }}" placeholder="fa-solid fa-washer">
                    @error('icon')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Giá cơ bản</label>
                    <input type="number" class="form-control" name="price" placeholder="25000" min="0" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Đơn vị tính</label>
                    <input type="text" class="form-control" name="unit" value="kg" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trạng thái</label>
                    <select class="form-select" name="status">
                        <option value="active">Đang hoạt động</option>
                        <option value="inactive">Tạm ngừng</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Mô tả</label>
                    <textarea class="form-control" name="description" rows="4" placeholder="Nhập mô tả dịch vụ..."></textarea>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-outline-secondary">Làm mới</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Lưu dịch vụ
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
