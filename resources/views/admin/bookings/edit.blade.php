@extends('layouts.app')

@section('title', 'Chỉnh sửa đặt lịch - Sky Laundry')
@section('page-title', 'Chỉnh sửa đặt lịch')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Chỉnh sửa đặt lịch</h5>
            <a href="{{ route('bookings.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('bookings.update', $booking->id) }}" method="POST">
            @csrf @method('PUT')
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Khách hàng</label>
                    <select class="form-select" name="customer_id">
                        <option value="">Chọn</option>
                        @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" {{ $booking->customer_id === $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Dịch vụ</label>
                    <select class="form-select" name="service_id">
                        <option value="">Chọn</option>
                        @foreach($services as $service)
                        <option value="{{ $service->id }}" {{ $booking->service_id === $service->id ? 'selected' : '' }}>{{ $service->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Loại đồ giặt</label>
                    <input type="text" class="form-control" name="garment_type" value="{{ old('garment_type', $booking->garment_type) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Số lượng</label>
                    <input type="number" class="form-control" name="quantity" value="{{ old('quantity', $booking->quantity) }}" min="1">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Giao nhận</label>
                    <select class="form-select" name="delivery_method">
                        <option value="pickup" {{ $booking->delivery_method === 'pickup' ? 'selected' : '' }}>Nhận tại tiệm</option>
                        <option value="dropoff" {{ $booking->delivery_method === 'dropoff' ? 'selected' : '' }}>Giao tận nơi</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Địa chỉ</label>
                    <input type="text" class="form-control" name="address" value="{{ old('address', $booking->address) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ngày lấy</label>
                    <input type="date" class="form-control" name="pickup_date" value="{{ old('pickup_date', $booking->pickup_date) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Giờ lấy</label>
                    <input type="time" class="form-control" name="pickup_time" value="{{ old('pickup_time', $booking->pickup_time) }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Ghi chú</label>
                    <textarea class="form-control" name="notes" rows="2">{{ old('notes', $booking->notes) }}</textarea>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-outline-secondary">Làm mới</button>
                <button type="submit" class="btn btn-primary">Cập nhật</button>
            </div>
        </form>
    </div>
</div>
@endsection
