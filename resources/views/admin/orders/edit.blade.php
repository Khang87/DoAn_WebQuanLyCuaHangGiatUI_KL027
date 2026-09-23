@extends('layouts.app')

@section('title', 'Chỉnh Sửa Đơn Hàng - Giặt Ủi Pro')
@section('page-title', 'Chỉnh Sửa Đơn Hàng')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Đơn hàng #DH001</h5>
            <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('orders.update', $order->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Mã đơn hàng</label>
                    <input type="text" class="form-control" name="code" value="{{ $order->code }}" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Khách hàng</label>
                    <select class="form-select" name="customer_id">
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected($customer->id === $order->customer_id)>{{ $customer->name }} ({{ $customer->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Chọn dịch vụ</label>
                    <p class="service-choice-help">Bạn có thể mô tả cụ thể về đồ cần giặt ở bước Ghi chú.</p>
                    <div class="service-choice-list">
                        @foreach($services as $service)
                            <label class="service-choice">
                                <input type="radio" name="service_id" value="{{ $service->id }}" @checked($service->id === $order->service_id) required>
                                <span>{{ $service->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Khối lượng</label>
                    <input type="text" class="form-control" name="weight_kg" value="{{ $order->weight_kg }}">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Mô tả số lượng</label>
                    <input type="text" class="form-control" name="quantity_items" value="{{ $order->quantity_items }}" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Tổng tiền</label>
                    <input type="number" class="form-control" name="total_amount" value="{{ $order->total_amount }}" min="0" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Trạng thái</label>
                    <select class="form-select" name="status">
                        <option value="pending">Chờ xử lý</option>
                        <option value="processing" @selected($order->status === 'processing')>Đang xử lý</option>
                        <option value="completed">Hoàn thành</option>
                        <option value="cancelled">Đã hủy</option>
                    </select>
                </div>

            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('orders.index') }}" class="btn btn-order-secondary order-form-btn">Hủy</a>
                <button type="submit" class="btn btn-order-primary order-form-btn">
                    <i class="bi bi-save me-1"></i>Cập nhật
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
