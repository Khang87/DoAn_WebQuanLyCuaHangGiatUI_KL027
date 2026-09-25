@extends('layouts.app')

@section('title', 'Sửa Giao Nhận - Sky Laundry')
@section('page-title', 'Sửa giao nhận')

@section('content')
<div class="delivery-form-shell">
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h5 mb-0">Cập nhật lịch giao nhận</h2>
                <div>
                    <span class="text-muted">Mã giao nhận</span>
                    <h4 class="mb-0">{{ $delivery->code ?? ('GH' . str_pad($delivery->id, 3, '0', STR_PAD_LEFT)) }}</h4>
                </div>
            </div>
            <form action="{{ route('deliveries.update', $delivery) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-4">
                    <label class="form-label">Hình thức giao nhận</label>
                    <div class="delivery-methods d-flex gap-3">
                        <label class="delivery-method form-check">
                            <input type="radio" name="method" value="pickup" {{ ($delivery->method === 'pickup') ? 'checked' : '' }}>
                            <i class="bi bi-shop me-1"></i>
                            <span>Khách nhận tại cửa hàng</span>
                        </label>
                        <label class="delivery-method form-check">
                            <input type="radio" name="method" value="home_pickup" {{ ($delivery->method === 'home_pickup') ? 'checked' : '' }}>
                            <i class="bi bi-truck me-1"></i>
                            <span>Đến lấy đồ tận nhà</span>
                        </label>
                        <label class="delivery-method form-check">
                            <input type="radio" name="method" value="dropoff" {{ ($delivery->method === 'dropoff') ? 'checked' : '' }}>
                            <i class="bi bi-geo-alt me-1"></i>
                            <span>Giao tận nơi</span>
                        </label>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Địa chỉ lấy &amp; giao đồ</label>
                        <input type="text" class="form-control" name="address" value="{{ $delivery->address ?? '' }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ngày lấy đồ</label>
                        <input type="date" class="form-control" name="pickup_date" value="{{ $delivery->pickup_date?->format('Y-m-d') ?? '' }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Giờ lấy đồ</label>
                        <input type="time" class="form-control" name="pickup_time" value="{{ $delivery->pickup_time?->format('H:i') ?? '' }}" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Trạng thái</label>
                        <select class="form-select" name="status">
                            <option value="pending" {{ ($delivery->status === 'pending') ? 'selected' : '' }}>Chờ xác nhận</option>
                            <option value="picking" {{ ($delivery->status === 'picking') ? 'selected' : '' }}>Đang lấy hàng</option>
                            <option value="delivering" {{ ($delivery->status === 'delivering') ? 'selected' : '' }}>Đang giao hàng</option>
                            <option value="completed" {{ ($delivery->status === 'completed') ? 'selected' : '' }}>Hoàn thành</option>
                            <option value="cancelled" {{ ($delivery->status === 'cancelled') ? 'selected' : '' }}>Đã hủy</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="form-label">Ghi chú</label>
                    <textarea class="form-control" name="notes" rows="3">{{ $delivery->notes ?? '' }}</textarea>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('deliveries.show', $delivery) }}" class="btn btn-outline-secondary">Hủy</a>
                    <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
