@extends('layouts.app')
@section('title', 'Sửa giao nhận - Sky Laundry')
@section('page-title', 'Sửa giao nhận')

@push('styles')
<style>
    .delivery-fixed-field {
        background-color: #f5f5f5;
        cursor: not-allowed;
    }
</style>
@endpush

@section('content')
@php
    $order = $delivery->donHang;
    $orderLabel = $order
        ? $order->MaDonHang.' · '.($order->khachHang?->HoTen ?: 'Khách hàng')
        : 'Không tìm thấy thông tin đơn hàng';
    $methodLabel = match ($delivery->LoaiGiaoNhan) {
        'NHAN_DO' => 'Nhận đồ',
        'GIAO_DO' => 'Giao đồ',
        default => '—',
    };
    $assignedEmployeeName = $assignedEmployee?->HoTen ?? $delivery->nhanVien?->HoTen ?? 'Chưa phân công';
    $employeeOptions = $employees ?? \App\Models\NhanVien::where('TrangThai', 'Hoạt động')->orderBy('HoTen')->get();
    $statusOptions ??= \App\Enums\DeliveryStatus::options();
@endphp
<div class="delivery-form-shell"><div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-4"><h2 class="h5 mb-0">Cập nhật lịch giao nhận</h2></div>
    <form action="{{ route('deliveries.update', $delivery) }}" method="POST">@csrf @method('PUT')
        <input type="hidden" name="order_id" value="{{ $delivery->DonHangID }}">
        <input type="hidden" name="method" value="{{ strtolower($delivery->LoaiGiaoNhan) }}">
        <input type="hidden" name="fulfillment" value="{{ $delivery->HinhThuc }}">
        @if($errors->any())
            <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
        @endif
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="order_display">Đơn hàng <span class="text-danger">*</span></label>
                <input class="form-control delivery-fixed-field" id="order_display" value="{{ $orderLabel }}" readonly aria-readonly="true">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="employee_id">Nhân viên phụ trách <span class="text-danger">*</span></label>
                @if($canAssignEmployee ?? true)
                    <select class="form-select" id="employee_id" name="employee_id" required><option value="">Chọn nhân viên</option>@foreach($employeeOptions as $employee)<option value="{{ $employee->NhanVienID }}" @selected(old('employee_id', $delivery->NhanVienID) == $employee->NhanVienID)>{{ $employee->HoTen }}</option>@endforeach</select>
                @else
                    <input class="form-control delivery-fixed-field" id="employee_id" value="{{ $assignedEmployeeName }}" readonly aria-readonly="true">
                    <input type="hidden" name="employee_id" value="{{ $assignedEmployee->NhanVienID }}">
                @endif
            </div>
            <div class="col-12">
                <label class="form-label" for="method_display">Loại giao nhận <span class="text-danger ms-1">*</span></label>
                <input class="form-control delivery-fixed-field" id="method_display" value="{{ $methodLabel }}" readonly aria-readonly="true">
            </div>
            <div class="col-12">
                <label class="form-label" for="fulfillment_display">Hình thức</label>
                <input class="form-control delivery-fixed-field" id="fulfillment_display" value="{{ $delivery->HinhThuc }}" readonly aria-readonly="true">
            </div>
            <div class="col-12"><label class="form-label" for="address">Địa chỉ giao nhận (bắt buộc khi tại nhà)</label><input type="text" class="form-control" id="address" name="address" value="{{ old('address', $delivery->DiaChi) }}" @required(old('fulfillment', $delivery->HinhThuc) === 'Tại nhà') @disabled(old('fulfillment', $delivery->HinhThuc) === 'Tại cửa hàng')></div>
            <div class="col-md-6"><label class="form-label" for="pickup_date">Ngày giao nhận</label><input type="date" class="form-control" id="pickup_date" name="pickup_date" aria-describedby="schedule-help" value="{{ old('pickup_date', $delivery->ThoiGianDuKien?->format('Y-m-d')) }}">@error('pickup_date')<div class="text-danger small">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="pickup_time">Giờ giao nhận</label><input type="time" class="form-control" id="pickup_time" name="pickup_time" aria-describedby="schedule-help" value="{{ old('pickup_time', $delivery->ThoiGianDuKien?->format('H:i')) }}">@error('pickup_time')<div class="text-danger small">{{ $message }}</div>@enderror</div>
            <div class="col-12"><label class="form-label" for="status">Trạng thái</label><x-admin.status-select name="status" id="status" :options="$statusOptions" :selected="\App\Enums\DeliveryStatus::parseForLeg($delivery->TrangThai, $delivery->LoaiGiaoNhan)->value" class="form-select" /></div>
            <div class="col-12 form-text" id="schedule-help" aria-live="polite">Phiếu trả đang chờ có thể chưa đặt lịch. Khi đặt lịch, hãy nhập đủ ngày và giờ.</div>
            <div class="col-12"><label class="form-label" for="notes">Ghi chú</label><textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes', $delivery->GhiChu) }}</textarea></div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('deliveries.show', $delivery) }}" class="btn btn-outline-secondary">Hủy</a><button type="submit" class="btn btn-primary">Lưu thay đổi</button></div>
    </form>
</div></div></div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/delivery-schedule.js') }}"></script>
@endpush
