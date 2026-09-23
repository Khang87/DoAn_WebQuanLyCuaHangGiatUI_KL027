@extends('layouts.app')

@section('title', 'Chi Tiết Giao Nhận - Giặt Ủi Pro')
@section('page-title', 'Chi tiết giao nhận')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <span class="text-muted">Mã giao nhận</span>
                <h4 class="mb-0">{{ $delivery->id ? 'GH' . str_pad($delivery->id, 3, '0', STR_PAD_LEFT) : 'GH001' }}</h4>
            </div>
            <span class="badge-status badge-{{ $delivery->status === 'completed' ? 'completed' : 'pending' }}">{{ $delivery->status === 'completed' ? 'Đã giao' : 'Chờ lấy đồ' }}</span>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="small text-muted">Khách hàng</div>
                <div class="fw-semibold">{{ $delivery->customer?->name ?? 'Chưa có' }}</div>
                <div class="text-muted">{{ $delivery->customer?->phone ?? 'Chưa có' }}</div>
            </div>
            <div class="col-md-6">
                <div class="small text-muted">Hình thức</div>
                <div class="fw-semibold"><i class="bi bi-{{ $delivery->method === 'dropoff' ? 'shop' : 'truck' }} me-1"></i>{{ $delivery->method === 'dropoff' ? 'Mang đến cửa hàng' : 'Lấy tận nơi' }}</div>
            </div>
            <div class="col-12">
                <div class="small text-muted">Địa chỉ</div>
                <div class="fw-semibold">{{ $delivery->address ?? 'Chưa có' }}</div>
            </div>
            @if($delivery->pickup_date)
            <div class="col-md-6">
                <div class="small text-muted">Ngày lấy đồ</div>
                <div class="fw-semibold">{{ $delivery->pickup_date->format('d/m/Y') }}</div>
            </div>
            @endif
            @if($delivery->pickup_time)
            <div class="col-md-6">
                <div class="small text-muted">Giờ lấy đồ</div>
                <div class="fw-semibold">{{ $delivery->pickup_time }}</div>
            </div>
            @endif
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('deliveries.index') }}" class="btn btn-outline-secondary">Quay lại</a>
            <a href="{{ route('deliveries.edit', $delivery) }}" class="btn btn-primary">Chỉnh sửa</a>
        </div>
    </div>
</div>
@endsection
