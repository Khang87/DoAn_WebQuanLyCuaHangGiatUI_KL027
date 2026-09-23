@extends('layouts.app')

@section('title', 'Chỉnh Sửa Dịch Vụ - Giặt Ủi Pro')
@section('page-title', 'Chỉnh Sửa Dịch Vụ')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Giặt Ủi Thường</h5>
            <a href="{{ route('services.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('services.update', 1) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Tên dịch vụ</label>
                    <input type="text" class="form-control" name="name" value="Giặt Ủi Thường" required>
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
                    <input type="text" class="form-control" name="price" value="25,000 VNĐ" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trạng thái</label>
                    <select class="form-select" name="status">
                        <option value="active" selected>Đang hoạt động</option>
                        <option value="inactive">Tạm ngừng</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Mô tả</label>
                    <textarea class="form-control" name="description" rows="4">Dịch vụ giặt ủi cơ bản cho quần áo hàng ngày. Thời gian xử lý 2-3 ngày.</textarea>
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
