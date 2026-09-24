@extends('layouts.app')

@section('title', 'Chi tiết đặt lịch - Giặt Ủi Pro')
@section('page-title', 'Chi tiết đặt lịch')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('bookings.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Quay lại
    </a>
    <a href="{{ route('bookings.edit', $booking->id) }}" class="btn btn-primary btn-sm">
        <i class="bi bi-pencil me-1"></i>Chỉnh sửa
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr><td><strong>Khách hàng</strong></td><td>{{ $booking->customer?->name }}</td></tr>
                    <tr><td><strong>Dịch vụ</strong></td><td>{{ $booking->service?->name }}</td></tr>
                    <tr><td><strong>Loại đồ giặt</strong></td><td>{{ $booking->garment_type }}</td></tr>
                    <tr><td><strong>Số lượng</strong></td><td>{{ $booking->quantity }}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr><td><strong>Giao nhận</strong></td><td>{{ $booking->delivery_method === 'pickup' ? 'Nhận tại tiệm' : 'Giao tận nơi' }}</td></tr>
                    <tr><td><strong>Địa chỉ</strong></td><td>{{ $booking->address ?: '-' }}</td></tr>
                    <tr><td><strong>Ngày lấy</strong></td><td>{{ $booking->pickup_date?->format('d/m/Y') }}</td></tr>
                    <tr><td><strong>Giờ lấy</strong></td><td>{{ $booking->pickup_time }}</td></tr>
                    <tr><td><strong>Trạng thái</strong></td>                        <td>
                            @if($booking->status === 'confirmed')
                                <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Đã xác nhận</span>
                            @elseif($booking->status === 'cancelled')
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-x-circle me-1"></i>Đã hủy</span>
                            @else
                                <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill"><i class="fas fa-hourglass me-1"></i>Chờ xác nhận</span>
                             @endif
                        </td></tr>
                </table>
            </div>
        </div>
        @if($booking->notes)
        <div class="mt-3"><strong>Ghi chú:</strong> {{ $booking->notes }}</div>
        @endif
    </div>
</div>
@endsection
