@extends('layouts.app')

@section('title', 'Tạo đặt lịch - Giặt Ủi Pro')
@section('page-title', 'Tạo đặt lịch')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Đặt lịch giặt ủi</h5>
            <a href="{{ route('bookings.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('bookings.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Khách hàng <span class="text-danger">*</span></label>
                    <select class="form-select @error('customer_id') is-invalid @enderror" name="customer_id" required>
                        <option value="">Chọn khách hàng</option>
                        @foreach($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }} ({{ $customer->code }})</option>
                        @endforeach
                    </select>
                    @error('customer_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Dịch vụ <span class="text-danger">*</span></label>
                    <select class="form-select @error('service_id') is-invalid @enderror" name="service_id" required>
                        <option value="">Chọn dịch vụ</option>
                        @foreach($services as $service)
                        <option value="{{ $service->id }}">{{ $service->name }} - {{ number_format($service->price) }} VNĐ</option>
                        @endforeach
                    </select>
                    @error('service_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Loại đồ giặt <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('garment_type') is-invalid @enderror" name="garment_type" value="{{ old('garment_type') }}" placeholder="vd: Áo dài, Váy cưới..." required>
                    @error('garment_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Số lượng <span class="text-danger">*</span></label>
                    <input type="number" class="form-control @error('quantity') is-invalid @enderror" name="quantity" value="{{ old('quantity', 1) }}" min="1" required>
                    @error('quantity')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Giao nhận <span class="text-danger">*</span></label>
                    <select class="form-select @error('delivery_method') is-invalid @enderror" name="delivery_method" required>
                        <option value="pickup">Nhận tại tiệm</option>
                        <option value="dropoff">Giao tận nơi</option>
                    </select>
                    @error('delivery_method')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Địa chỉ giao</label>
                    <input type="text" class="form-control @error('address') is-invalid @enderror" name="address" value="{{ old('address') }}" placeholder="Địa chỉ giao tận nơi">
                    @error('address')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ngày lấy hàng <span class="text-danger">*</span></label>
                    <input type="date" class="form-control @error('pickup_date') is-invalid @enderror" name="pickup_date" value="{{ old('pickup_date') }}" required>
                    @error('pickup_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Giờ lấy hàng <span class="text-danger">*</span></label>
                    <input type="time" class="form-control @error('pickup_time') is-invalid @enderror" name="pickup_time" value="{{ old('pickup_time') }}" required>
                    @error('pickup_time')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Ghi chú</label>
                    <textarea class="form-control @error('notes') is-invalid @enderror" name="notes" rows="3">{{ old('notes') }}</textarea>
                    @error('notes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-outline-secondary">Làm mới</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Tạo đặt lịch
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
