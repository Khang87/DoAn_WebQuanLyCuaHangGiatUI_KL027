@extends('layouts.app')

@section('title', 'Thêm khuyến mãi - Giặt Ủi Pro')
@section('page-title', 'Thêm khuyến mãi')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Thông tin chương trình</h5>
            <a href="{{ route('promotions.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('promotions.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Tên chương trình</label>
                    <input type="text" class="form-control" name="name" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mã khuyến mãi</label>
                    <input type="text" class="form-control" name="code" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mức giảm</label>
                    <input type="text" class="form-control" name="discount" placeholder="10% hoặc 50,000 VNĐ" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ngày hết hạn</label>
                    <input type="date" class="form-control" name="expires_at" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trạng thái</label>
                    <select class="form-select" name="status">
                        <option value="active" selected>Đang chạy</option>
                        <option value="inactive">Tắt</option>
                    </select>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('promotions.index') }}" class="btn btn-outline-secondary">Hủy</a>
                <button type="submit" class="btn btn-primary">Lưu khuyến mãi</button>
            </div>
        </form>
    </div>
</div>
@endsection
