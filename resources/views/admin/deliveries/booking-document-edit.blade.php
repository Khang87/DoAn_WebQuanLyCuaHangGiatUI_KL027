@extends('layouts.app')

@section('title', 'Sửa phiếu nhận đồ - Sky Laundry')
@section('page-title', 'Sửa phiếu nhận đồ')

@section('content')
@php
    $booking = $bookingDocument->booking;
    $employee = $bookingDocument->nhanVien;
@endphp

<div class="delivery-form-shell">
    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <div>
                    <h2 class="h5 mb-1">Cập nhật phiếu nhận đồ</h2>
                    <p class="text-muted mb-0">{{ $displayCode ?? 'BGN-'.$bookingDocument->BookingGiaoNhanID }} · {{ $booking?->MaBooking ?: 'Đặt lịch' }}</p>
                </div>
                <a href="{{ route('deliveries.booking-documents.show', $bookingDocument) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Quay lại
                </a>
            </div>

            @if($errors->any())
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        @foreach($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('deliveries.booking-documents.update', $bookingDocument) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="booking_display">Đặt lịch</label>
                        <input class="form-control" id="booking_display" value="{{ $booking?->MaBooking ?: '—' }}" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="fulfillment_display">Hình thức nhận đồ</label>
                        <input class="form-control" id="fulfillment_display" value="{{ $bookingDocument->HinhThuc }}" readonly>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="employee_id">Nhân viên phụ trách</label>
                        @if($canAssignEmployee)
                            @php
                                $duplicateEmployeeNames = $employees
                                    ->countBy(fn ($candidate) => mb_strtolower(trim((string) $candidate->HoTen)));
                                $currentUserEmployeeId = (int) (auth()->user()?->NhanVienID ?? 0);
                            @endphp
                            <select class="form-select" id="employee_id" name="employee_id" required>
                                <option value="">Chọn nhân viên</option>
                                @foreach($employees as $candidate)
                                    <option value="{{ $candidate->NhanVienID }}" @selected((string) old('employee_id', $bookingDocument->NhanVienID) === (string) $candidate->NhanVienID)>
                                        @if((int) $candidate->NhanVienID === $currentUserEmployeeId)
                                            Bản thân tôi —
                                        @endif
                                        {{ $candidate->HoTen }}{{ $candidate->ChucDanh ? ' · '.$candidate->ChucDanh : '' }}@if(($duplicateEmployeeNames[mb_strtolower(trim((string) $candidate->HoTen))] ?? 0) > 1) · {{ $candidate->SoDienThoai ?: ($candidate->Email ?: 'Mã NV '.$candidate->NhanVienID) }}@endif
                                    </option>
                                @endforeach
                            </select>
                            @error('employee_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        @else
                            <input class="form-control" id="employee_id" value="{{ $employee?->HoTen ?: 'Chưa phân công' }}" readonly>
                        @endif
                    </div>

                    @if($bookingDocument->HinhThuc === 'Tại nhà')
                        <div class="col-12">
                            <label class="form-label" for="address">Địa chỉ nhận đồ <span class="text-danger">*</span></label>
                            <input class="form-control" id="address" name="address" value="{{ old('address', $bookingDocument->DiaChi) }}" required maxlength="255">
                            @error('address')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                    @endif

                    <div class="col-md-6">
                        <label class="form-label" for="pickup_date">Ngày dự kiến</label>
                        <input type="date" class="form-control" id="pickup_date" name="pickup_date"
                               value="{{ old('pickup_date', $bookingDocument->ThoiGianDuKien?->format('Y-m-d')) }}">
                        @error('pickup_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="pickup_time">Giờ dự kiến</label>
                        <input type="time" class="form-control" id="pickup_time" name="pickup_time"
                               value="{{ old('pickup_time', $bookingDocument->ThoiGianDuKien?->format('H:i')) }}">
                        @error('pickup_time')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-primary">Cập nhật</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
