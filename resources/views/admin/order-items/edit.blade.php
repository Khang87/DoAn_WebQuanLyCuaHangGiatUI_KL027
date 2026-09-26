@extends('layouts.app')

@section('title', 'Chỉnh sửa chi tiết - Sky Laundry')
@section('page-title', 'Chỉnh sửa chi tiết')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Chỉnh sửa</h5>
            <a href="{{ route('order-items.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('order-items.update', $item->id) }}" method="POST">
            @csrf @method('PUT')
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Đơn hàng</label>
                    <select class="form-select" name="order_id">
                        <option value="">-- Chọn đơn hàng --</option>
                        @foreach($orders as $order)
                        <option value="{{ $order->id }}" {{ $item->order_id === $order->id ? 'selected' : '' }}>{{ $order->code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Dịch vụ</label>
                    <select class="form-select" name="service_id">
                        <option value="">-- Chọn dịch vụ --</option>
                        @foreach($services as $service)
                        <option value="{{ $service->id }}" {{ $item->service_id === $service->id ? 'selected' : '' }}>{{ $service->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tên mặt hàng <span class="text-danger ms-1">*</span></label>
                    <input type="text" class="form-control" name="item_name" value="{{ old('item_name', $item->item_name) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Loại</label>
                    <select class="form-select" name="item_type">
                        <option value="garment" {{ $item->item_type === 'garment' ? 'selected' : '' }}>Đồ giặt</option>
                        <option value="service" {{ $item->item_type === 'service' ? 'selected' : '' }}>Dịch vụ</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Đơn giá <span class="text-danger ms-1">*</span></label>
                    <input type="number" class="form-control" name="price" value="{{ old('price', (int) round((float) $item->price)) }}" min="0" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Số lượng <span class="text-danger ms-1">*</span></label>
                    <input type="number" class="form-control" name="quantity" value="{{ old('quantity', $item->quantity) }}" min="1" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Ghi chú</label>
                    <textarea class="form-control" name="notes" rows="2">{{ old('notes', $item->notes) }}</textarea>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('order-items.index') }}" class="btn btn-outline-secondary">Hủy</a>
                <button type="submit" class="btn btn-primary">Cập nhật</button>
            </div>
        </form>
    </div>
</div>
@endsection
