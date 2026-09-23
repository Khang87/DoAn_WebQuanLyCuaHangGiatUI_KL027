@extends('layouts.app')

@section('title', 'Chi Tiết Giao Nhận - Giặt Ủi Pro')
@section('page-title', 'Chi tiết giao nhận')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <span class="text-muted">Mã giao nhận</span>
                <h4 class="mb-0">GH{{ str_pad($id, 3, '0', STR_PAD_LEFT) }}</h4>
            </div>
            <span class="badge-status badge-pending">Chờ lấy đồ</span>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="small text-muted">Khách hàng</div>
                <div class="fw-semibold">Nguyễn Văn A</div>
                <div class="text-muted">0901234567</div>
            </div>
            <div class="col-md-6">
                <div class="small text-muted">Hình thức</div>
                <div class="fw-semibold"><i class="bi bi-truck me-1"></i>Lấy tận nơi</div>
            </div>
            <div class="col-12">
                <div class="small text-muted">Địa chỉ lấy &amp; giao đồ</div>
                <div class="fw-semibold">123 Đường Điện Biên Phủ, Phường 15, Bình Thạnh, TP.HCM</div>
            </div>
            <div class="col-md-6">
                <div class="small text-muted">Ngày lấy đồ</div>
                <div class="fw-semibold">25/09/2026</div>
            </div>
            <div class="col-md-6">
                <div class="small text-muted">Giờ lấy đồ</div>
                <div class="fw-semibold">09:00</div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('deliveries.index') }}" class="btn btn-outline-secondary">Quay lại</a>
            <a href="{{ route('deliveries.edit', $id) }}" class="btn btn-primary">Chỉnh sửa</a>
        </div>
    </div>
</div>
@endsection
