@extends('layouts.app')

@section('title', 'Chi Tiết Dịch Vụ - Giặt Ủi Pro')
@section('page-title', 'Chi Tiết Dịch Vụ')

@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0">Giặt Ủi Thường</h5>
                    <span class="badge bg-success">Đang hoạt động</span>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="small text-muted">Loại dịch vụ</div>
                        <div class="fw-semibold">Giặt</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Giá cơ bản</div>
                        <div class="fw-semibold text-primary">25,000 VNĐ/kg</div>
                    </div>
                    <div class="col-12">
                        <div class="small text-muted">Mô tả</div>
                        <div>Dịch vụ giặt ủi cơ bản cho quần áo hàng ngày. Thời gian xử lý 2-3 ngày.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h6 class="mb-3">Thao tác</h6>
                <div class="d-grid gap-2">
                    <a href="{{ route('services.edit', 1) }}" class="btn btn-warning">Chỉnh sửa</a>
                    <form action="{{ route('services.destroy', 1) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa dịch vụ?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger w-100">Xóa dịch vụ</button>
                    </form>
                    <a href="{{ route('services.index') }}" class="btn btn-outline-secondary">Quay lại danh sách</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
