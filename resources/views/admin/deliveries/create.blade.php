@extends('layouts.app')
@section('title', 'Tạo lịch giao Nhận - Sky Laundry')
@section('page-title', 'Tạo lịch giao nhận')
@section('content')
@php
    $orderOptions = $orders ?? \App\Models\DonHang::orderByDesc('NgayTao')->get();
    $employeeOptions = $employees ?? \App\Models\NhanVien::where('TrangThai', 'Hoạt động')->orderBy('HoTen')->get();
@endphp
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-4"><h5 class="mb-0">Thông tin lịch giao nhận</h5><a href="{{ route('deliveries.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Quay lại</a></div>
    <form action="{{ route('deliveries.store') }}" method="POST" data-booking-prefill>@csrf
        @if($errors->any())
            <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
        @endif
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="order_id">Đơn hàng <span class="text-danger">*</span></label><select class="form-select" id="order_id" name="order_id" required><option value="">Chọn đơn hàng</option>@foreach($orderOptions as $order)<option value="{{ $order->DonHangID }}" data-booking-id="{{ $order->booking?->BookingID }}" data-receive-method="{{ $order->booking?->HinhThucNhanDo }}" data-receive-address="{{ $order->booking?->DiaChiNhan }}" data-return-method="{{ $order->booking?->HinhThucTraDo }}" data-return-address="{{ $order->booking?->DiaChiTra }}" data-pickup-date="{{ $order->booking?->NgayHen?->format('Y-m-d') }}" data-pickup-time="{{ $order->booking?->GioHen?->format('H:i') }}" @selected(old('order_id') == $order->DonHangID)>{{ $order->MaDonHang }} · {{ $order->khachHang?->HoTen ?: 'Khách hàng' }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label" for="employee_id">Nhân viên phụ trách</label><select class="form-select" id="employee_id" name="employee_id"><option value="">Chọn nhân viên</option>@foreach($employeeOptions as $employee)<option value="{{ $employee->NhanVienID }}" @selected(old('employee_id') == $employee->NhanVienID)>{{ $employee->HoTen }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label" for="method">Loại giao nhận <span class="text-danger ms-1">*</span></label><select class="form-select" id="method" name="method" required><option value="nhan_do" @selected(old('method', 'nhan_do') === 'nhan_do')>Nhận đồ</option><option value="giao_do" @selected(old('method') === 'giao_do')>Giao đồ</option></select></div>
            <div class="col-12"><label class="form-label" for="fulfillment">Hình thức</label><select class="form-select" id="fulfillment" name="fulfillment" required><option value="Tại cửa hàng" @selected(old('fulfillment', 'Tại nhà') === 'Tại cửa hàng')>Tại cửa hàng</option><option value="Tại nhà" @selected(old('fulfillment', 'Tại nhà') === 'Tại nhà')>Tại nhà</option></select></div>
            <div class="col-12"><label class="form-label" for="address">Địa chỉ (bắt buộc khi tại nhà)</label><input type="text" class="form-control" id="address" name="address" value="{{ old('address') }}" placeholder="Nhập địa chỉ lấy/giao đồ" @required(old('fulfillment', 'Tại nhà') === 'Tại nhà') @disabled(old('fulfillment', 'Tại nhà') === 'Tại cửa hàng')></div>
            <div class="col-md-6"><label class="form-label" for="pickup_date">Ngày giao nhận</label><input type="date" class="form-control" id="pickup_date" name="pickup_date" aria-describedby="schedule-help" value="{{ old('pickup_date') }}">@error('pickup_date')<div class="text-danger small">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="pickup_time">Giờ giao nhận</label><input type="time" class="form-control" id="pickup_time" name="pickup_time" aria-describedby="schedule-help" value="{{ old('pickup_time') }}">@error('pickup_time')<div class="text-danger small">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="status">Trạng thái</label><x-admin.status-select name="status" id="status" :options="\App\Enums\DeliveryStatus::options()" selected="pending" class="form-select" /></div>
            <div class="col-12 form-text" id="schedule-help" aria-live="polite">Phiếu trả đang chờ có thể chưa đặt lịch. Khi đặt lịch, hãy nhập đủ ngày và giờ.</div>
            <div class="col-12 form-text">Khi chọn đơn có Booking, hình thức, địa chỉ và lịch nhận (nếu có) sẽ được điền sẵn; bạn có thể điều chỉnh trước khi lưu.</div>
            <div class="col-12"><label class="form-label" for="notes">Ghi chú</label><textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea></div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('deliveries.index') }}" class="btn btn-outline-secondary">Hủy</a><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Lưu lịch giao nhận</button></div>
    </form>
</div></div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/delivery-schedule.js') }}"></script>
@endpush
