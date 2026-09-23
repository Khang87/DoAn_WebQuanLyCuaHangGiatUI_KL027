@extends('layouts.app')

@section('title', 'Chi Tiết Đơn Hàng - Giặt Ủi Pro')
@section('page-title', 'Chi Tiết Đơn Hàng')

@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0">Đơn hàng #DH001</h5>
                    <span class="badge-status badge-processing">Đang xử lý</span>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="small text-muted">Khách hàng</div>
                        <div class="fw-semibold">Nguyễn Văn A</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Số điện thoại</div>
                        <div class="fw-semibold">0901234567</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Dịch vụ</div>
                        <div class="fw-semibold">Giặt ủi thường</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Khối lượng</div>
                        <div class="fw-semibold">5kg</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Mô tả số lượng</div>
                        <div class="fw-semibold">đồ thường + 3 áo trắng</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Tổng tiền</div>
                        <div class="fw-semibold text-primary">250,000 VNĐ</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Ngày tạo</div>
                        <div class="fw-semibold">20/09/2024</div>
                    </div>
                    <div class="col-12">
                        <div class="small text-muted">Ghi chú</div>
                        <div>Khách yêu cầu giặt trước 18h tối nay.</div>
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
                    <a href="{{ route('orders.edit', 1) }}" class="btn btn-order-secondary order-form-btn">Chỉnh sửa</a>
                    <form action="{{ route('orders.destroy', 1) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa đơn hàng?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger w-100 order-form-btn">Xóa đơn hàng</button>
                    </form>
                    <a href="{{ route('orders.index') }}" class="btn btn-order-primary order-form-btn">Quay lại danh sách</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
