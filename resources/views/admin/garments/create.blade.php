@extends('layouts.app')

@section('title', 'Thêm Loại Đồ Giặt - Giặt Ủi Pro')
@section('page-title', 'Thêm Loại Đồ Giặt & Hiện Trạng')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Thông tin loại đồ giặt</h5>
            <a href="{{ route('garments.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('garments.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Tên loại đồ giặt / sản phẩm</label>
                    <input type="text" class="form-control" name="name" placeholder="VD: Áo dài, Váy cưới, Áo khoác dạ, Quần âu..." required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Danh mục loại đồ</label>
                    <input type="text" class="form-control" name="category" placeholder="VD: Đồ cao cấp, Đồ thường, Đồ nặng...">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Đơn giá dịch vụ (VNĐ)</label>
                    <input type="number" class="form-control" name="price" placeholder="50000" min="0" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Trạng thái</label>
                    <select class="form-select" name="status">
                        <option value="active">Đang hoạt động</option>
                        <option value="inactive">Tạm ngưng</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Mô tả hiện trạng trước khi giặt (Ghi chú rách, ố, cũ, sờn...)</label>
                    <textarea class="form-control" name="condition_note" rows="4" placeholder="Nhập mô tả tình trạng sản phẩm trước khi tiếp nhận giặt..."></textarea>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-outline-secondary">Làm mới</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Lưu thông tin
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
