@extends('layouts.app')

@section('title', 'Chi Tiết Đơn Hàng - Giặt Ủi Pro')
@section('page-title', 'Chi Tiết Đơn Hàng')

@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0">Đơn hàng #{{ $order->code }}</h5>
                    <span class="badge-status badge-processing">{{ $order->status }}</span>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="small text-muted">Khách hàng</div>
                        <div class="fw-semibold">{{ $order->customer?->name ?: '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Số điện thoại</div>
                        <div class="fw-semibold">{{ $order->customer?->phone ?: '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Dịch vụ</div>
                        <div class="fw-semibold">{{ $order->service?->name ?: '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Khối lượng</div>
                        <div class="fw-semibold">{{ $order->weight_kg ?: '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Mô tả số lượng</div>
                        <div class="fw-semibold">{{ $order->quantity_items ?: '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Tổng tiền</div>
                        <div class="fw-semibold text-primary">{{ number_format($order->total_amount) }} VNĐ</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Ngày tạo</div>
                        <div class="fw-semibold">{{ $order->created_at?->format('d/m/Y') }}</div>
                    </div>
                    <div class="col-12">
                        <div class="small text-muted">Ghi chú từ khách hàng</div>
                        <div>{{ $order->notes ?: 'Không có ghi chú.' }}</div>
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
                    <a href="{{ route('orders.edit', $order) }}" class="btn btn-order-secondary order-form-btn">Chỉnh sửa</a>
                    <form action="{{ route('orders.destroy', $order) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa đơn hàng?')">
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
