@extends('layouts.app')
@section('title', 'Tạo lịch giao Nhận - Sky Laundry')
@section('page-title', 'Tạo lịch giao nhận')
@section('content')
@php
    $orderOptions = $orders ?? \App\Models\Order::orderByDesc('created_at')->get();
    $employeeOptions = $employees ?? $staffUsers ?? \App\Models\User::where('role', 'staff')->orderBy('name')->get();
@endphp
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-4"><h5 class="mb-0">Thông tin lịch giao nhận</h5><a href="{{ route('deliveries.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Quay lại</a></div>
    <form action="{{ route('deliveries.store') }}" method="POST">@csrf
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="order_id">Đơn hàng <span class="text-danger">*</span></label><select class="form-select" id="order_id" name="order_id" required><option value="">-- Chọn đơn hàng --</option>@foreach($orderOptions as $order)<option value="{{ $order->id }}" @selected(old('order_id') == $order->id)>{{ $order->code }} · {{ $order->customer?->name ?: 'Khách hàng' }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label" for="employee_id">Nhân viên phụ trách</label><select class="form-select" id="employee_id" name="employee_id"><option value="">-- Chọn nhân viên --</option>@foreach($employeeOptions as $employee)<option value="{{ $employee->id }}" @selected(old('employee_id') == $employee->id)>{{ $employee->name }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label" for="method">Hình thức giao nhận <span class="text-danger ms-1">*</span></label><select class="form-select" id="method" name="method" required><option value="nhan_do" @selected(old('method', 'nhan_do') === 'nhan_do')>Nhận đồ</option><option value="giao_do" @selected(old('method') === 'giao_do')>Giao đồ</option></select></div>
            <div class="col-12"><label class="form-label" for="address">Địa chỉ <span class="text-danger ms-1">*</span></label><input type="text" class="form-control" id="address" name="address" value="{{ old('address') }}" placeholder="Nhập địa chỉ lấy/giao đồ" required></div>
            <div class="col-md-6"><label class="form-label" for="pickup_date">Ngày giao nhận <span class="text-danger ms-1">*</span></label><input type="date" class="form-control" id="pickup_date" name="pickup_date" value="{{ old('pickup_date') }}" required></div>
            <div class="col-md-6"><label class="form-label" for="pickup_time">Giờ giao nhận <span class="text-danger ms-1">*</span></label><input type="time" class="form-control" id="pickup_time" name="pickup_time" value="{{ old('pickup_time') }}" required></div>
            <div class="col-md-6"><label class="form-label" for="status">Trạng thái</label><x-admin.status-select name="status" id="status" :options="\App\Enums\DeliveryStatus::options()" selected="pending" class="form-select" /></div>
            <div class="col-12"><label class="form-label" for="notes">Ghi chú</label><textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea></div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('deliveries.index') }}" class="btn btn-outline-secondary">Hủy</a><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Lưu lịch giao nhận</button></div>
    </form>
</div></div>
@endsection