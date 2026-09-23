@extends('layouts.app')

@section('title', 'Thêm chi tiết đơn hàng - Giặt Ủi Pro')
@section('page-title', 'Thêm chi tiết đơn hàng')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Thêm chi tiết</h5>
            <a href="{{ route('order-items.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('order-items.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Đơn hàng</label>
                    <select class="form-select" name="order_id">
                        <option value="">Chọn đơn hàng</option>
                        @foreach($orders as $order)
                        <option value="{{ $order->id }}">#{{ $order->code }} - {{ $order->customer?->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Dịch vụ</label>
                    <select class="form-select" name="service_id">
                        <option value="">Chọn dịch vụ</option>
                        @foreach($services as $service)
                        <option value="{{ $service->id }}">{{ $service->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tên mặt hàng</label>
                    <input type="text" class="form-control" name="item_name" placeholder="vd: Áo dài, Váy cưới" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Loại</label>
                    <select class="form-select" name="item_type">
                        <option value="garment">Đồ giặt</option>
                        <option value="service">Dịch vụ</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Đơn giá</label>
                    <input type="number" class="form-control" name="price" placeholder="25000" min="0" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Số lượng</label>
                    <input type="number" class="form-control" name="quantity" value="1" min="1" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Ghi chú</label>
                    <textarea class="form-control" name="notes" rows="2"></textarea>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('order-items.index') }}" class="btn btn-outline-secondary">Hủy</a>
                <button type="submit" class="btn btn-primary">Thêm</button>
            </div>
        </form>
    </div>
</div>
@endsection
