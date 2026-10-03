@extends('layouts.app')
@section('title', 'Sửa giao nhận - Sky Laundry')
@section('page-title', 'Sửa giao nhận')
@section('content')
@php
    $orderOptions = $orders ?? \App\Models\DonHang::orderByDesc('NgayTao')->get();
    $employeeOptions = $employees ?? \App\Models\NhanVien::where('TrangThai', 'Hoạt động')->orderBy('HoTen')->get();
@endphp
<div class="delivery-form-shell"><div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-4"><h2 class="h5 mb-0">Cập nhật lịch giao nhận</h2><div><span class="text-muted">Mã giao nhận</span><h4 class="mb-0">{{ $delivery->MaGiaoNhan ?? ('GH' . str_pad($delivery->GiaoNhanID, 3, '0', STR_PAD_LEFT)) }}</h4></div></div>
    <form action="{{ route('deliveries.update', $delivery) }}" method="POST">@csrf @method('PUT')
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="order_id">Đơn hàng <span class="text-danger">*</span></label><select class="form-select" id="order_id" name="order_id" required><option value="">Chọn đơn hàng</option>@foreach($orderOptions as $order)<option value="{{ $order->DonHangID }}" @selected(old('order_id', $delivery->DonHangID) == $order->DonHangID)>{{ $order->MaDonHang }} · {{ $order->khachHang?->HoTen ?: 'Khách hàng' }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label" for="employee_id">Nhân viên phụ trách</label><select class="form-select" id="employee_id" name="employee_id"><option value="">Chọn nhân viên</option>@foreach($employeeOptions as $employee)<option value="{{ $employee->NhanVienID }}" @selected(old('employee_id', $delivery->NhanVienID) == $employee->NhanVienID)>{{ $employee->HoTen }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label" for="method">Hình thức giao nhận <span class="text-danger ms-1">*</span></label><select class="form-select" id="method" name="method" required><option value="nhan_do" @selected(old('method', $delivery->HinhThuc) === 'nhan_do')>Nhận đồ</option><option value="giao_do" @selected(old('method', $delivery->HinhThuc) === 'giao_do')>Giao đồ</option></select></div>
            <div class="col-12"><label class="form-label" for="address">Địa chỉ lấy & giao đồ <span class="text-danger ms-1">*</span></label><input type="text" class="form-control" id="address" name="address" value="{{ old('address', $delivery->DiaChi) }}" required></div>
            <div class="col-md-6"><label class="form-label" for="pickup_date">Ngày giao nhận <span class="text-danger ms-1">*</span></label><input type="date" class="form-control" id="pickup_date" name="pickup_date" value="{{ old('pickup_date', $delivery->ThoiGianDuKien?->format('Y-m-d')) }}" required></div>
            <div class="col-md-6"><label class="form-label" for="pickup_time">Giờ giao nhận <span class="text-danger ms-1">*</span></label><input type="time" class="form-control" id="pickup_time" name="pickup_time" value="{{ old('pickup_time', $delivery->ThoiGianDuKien?->format('H:i')) }}" required></div>
            <div class="col-12"><label class="form-label" for="status">Trạng thái</label><x-admin.status-select name="status" id="status" :options="\App\Enums\DeliveryStatus::options()" :selected="$delivery->TrangThai" class="form-select" /></div>
            <div class="col-12"><label class="form-label" for="notes">Ghi chú</label><textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes', $delivery->GhiChu) }}</textarea></div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('deliveries.show', $delivery) }}" class="btn btn-outline-secondary">Hủy</a><button type="submit" class="btn btn-primary">Lưu thay đổi</button></div>
    </form>
</div></div></div>
@endsection