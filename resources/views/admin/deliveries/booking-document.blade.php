@extends('layouts.app')

@section('title', 'Chi tiết phiếu giao nhận - Sky Laundry')
@section('page-title', 'Chi tiết phiếu giao nhận')

@section('content')
@php
    $booking = $bookingDocument->booking;
    $isIntake = $bookingDocument->LoaiGiaoNhan === 'NHAN_DO';
    $isReceived = $isIntake && $bookingDocument->TrangThai === 'Đã nhận';
    $isCancelled = $bookingDocument->TrangThai === \App\Enums\DeliveryStatus::Cancelled->dbValue();
    $order = $bookingDocument->donHang ?? $booking?->donHangs->first();
@endphp

<x-admin.detail.page-header
    title="{{ $isIntake ? 'Phiếu nhận đồ từ đặt lịch' : 'Phiếu trả đồ từ đặt lịch' }}"
    :subtitle="$displayCode . ($booking?->MaBooking ? ' · Đặt lịch ' . $booking->MaBooking : '')"
>
    <x-slot:badge>
        @if($isReceived)
            <x-admin.status-badge
                :status="\App\Enums\DeliveryStatus::Completed"
                :enum="\App\Enums\DeliveryStatus::class"
                label="Đã nhận đồ"
            />
        @else
            <x-admin.status-badge :status="\App\Enums\DeliveryStatus::parseForLeg($bookingDocument->TrangThai, $bookingDocument->LoaiGiaoNhan)" :enum="\App\Enums\DeliveryStatus::class" />
        @endif
    </x-slot:badge>
</x-admin.detail.page-header>

<div class="row g-4">
    <div class="col-lg-8">
        <x-admin.detail.panel title="Thông tin phiếu giao nhận" icon="bi-receipt" :iconClass="'bg-primary-subtle text-primary'">
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Mã phiếu" :value="$displayCode" />
                <x-admin.detail.info-item label="Mã đặt lịch" :value="$booking?->MaBooking ?: '—'" />
                <x-admin.detail.info-item label="Khách hàng" :value="$booking?->khachHang?->HoTen ?: '—'" />
                <x-admin.detail.info-item label="Số điện thoại" :value="$booking?->khachHang?->SoDienThoai ?: '—'" />
                <x-admin.detail.info-item label="Hình thức" :value="$bookingDocument->HinhThuc ?: '—'" />
                <x-admin.detail.info-item label="Địa chỉ" :value="$bookingDocument->DiaChi ?: 'Không yêu cầu địa chỉ giao tận nhà'" />
                <x-admin.detail.info-item label="Nhân viên phụ trách" :value="$bookingDocument->nhanVien?->HoTen ?: 'Chưa phân công'" />
                <x-admin.detail.info-item label="Thời gian dự kiến" :value="$bookingDocument->ThoiGianDuKien?->format('d/m/Y H:i') ?: 'Chưa lên lịch'" />
                <x-admin.detail.info-item label="Thời gian thực tế" :value="$bookingDocument->ThoiGianThucTe?->format('d/m/Y H:i') ?: 'Chưa ghi nhận'" />
                <x-admin.detail.info-item label="Phí đã ghi nhận" :value="number_format((float) $bookingDocument->PhiGiaoNhan, 0, ',', '.') . ' VNĐ'" />
                <x-admin.detail.info-item label="Đơn hàng liên kết">
                    @if($order)
                        @can('orders.view')
                            <a href="{{ route('orders.show', $order) }}">{{ $order->MaDonHang }}</a>
                        @else
                            {{ $order->MaDonHang }}
                        @endcan
                    @else
                        <span class="text-muted">Chưa tạo đơn hàng</span>
                    @endif
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Trạng thái Booking">
                    @if($booking)
                        <x-admin.status-badge :status="$booking->TrangThai" :enum="\App\Enums\BookingStatus::class" :pill="false" />
                    @else
                        —
                    @endif
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>
        </x-admin.detail.panel>

        @if($booking)
            <x-admin.detail.panel title="Thông tin Booking" icon="bi-calendar-check" :iconClass="'bg-info-subtle text-info'">
                <x-admin.detail.info-grid :columns="2">
                    <x-admin.detail.info-item label="Nhận đồ" :value="$booking->method_label" />
                    <x-admin.detail.info-item label="Trả đồ" :value="$booking->return_method_label" />
                    <x-admin.detail.info-item label="Ngày hẹn" :value="$booking->NgayHen?->format('d/m/Y') ?: '—'" />
                    <x-admin.detail.info-item label="Ghi chú" :value="$booking->GhiChu ?: '—'" />
                </x-admin.detail.info-grid>
                @can('bookings.view')
                    <a class="btn btn-outline-secondary btn-sm mt-3" href="{{ route('bookings.show', $booking) }}">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Xem Booking
                    </a>
                @endcan
            </x-admin.detail.panel>
        @endif
    </div>

    <div class="col-lg-4">
        <x-admin.detail.panel title="Thao tác" icon="bi-sliders" :iconClass="'bg-secondary-subtle text-secondary'">
            @if($isIntake && $booking && $booking->statusEnum() === \App\Enums\BookingStatus::Confirmed && ! $order)
                @if($isReceived)
                    @can('bookings.confirm')
                        <a href="{{ route('bookings.inspection', $booking) }}" class="btn btn-primary w-100">
                            <i class="bi bi-clipboard-check me-1"></i>Kiểm tra và tạo đơn
                        </a>
                    @endcan
                @elseif(! $isCancelled)
                    @can('bookings.confirm')
                        <form action="{{ route('bookings.receive', $booking) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-primary w-100">Xác nhận đã nhận đồ</button>
                        </form>
                    @endcan
                    <p class="small text-muted mt-2 mb-0">
                        @if($bookingDocument->HinhThuc === 'Tại nhà')
                            Nhân viên ghi nhận tại đây sau khi đã lấy đồ thực tế từ khách.
                        @else
                            Ghi nhận sau khi khách đã mang đồ đến cửa hàng.
                        @endif
                    </p>
                @else
                    <p class="alert alert-danger mb-0">Phiếu nhận đồ đã hủy. Không thể xác nhận đã nhận đồ.</p>
                @endif
            @elseif($order)
                <p class="mb-0">Đơn hàng đã được tạo từ kiểm kê. Không thể tạo thêm đơn cho phiếu này.</p>
            @elseif(! $isIntake)
                <p class="mb-0">Đây là phiếu trả đồ từ đặt lịch, không phải phiếu giao trả vận hành.</p>
            @else
                <p class="mb-0">Phiếu chưa ở trạng thái cho phép ghi nhận hoặc kiểm kê.</p>
            @endif

            @if($canManageIntake)
                @can('deliveries.edit')
                    <a href="{{ route('deliveries.booking-documents.edit', $bookingDocument) }}"
                       class="btn btn-order-action edit mt-3" title="Sửa" aria-label="Sửa phiếu nhận đồ">
                        <i class="bi bi-pencil"></i>
                    </a>
                @endcan
            @elseif($isCancelled)
                <p class="small text-muted mt-3 mb-0">Phiếu đã được đánh dấu hủy trước đây; trạng thái Booking được quản lý riêng.</p>
            @elseif($booking?->statusEnum() === \App\Enums\BookingStatus::Cancelled)
                <p class="small text-muted mt-3 mb-0">Booking đã hủy. Phiếu được giữ lại để tra cứu lịch sử.</p>
            @endif

            <a href="{{ route('deliveries.index') }}" class="btn btn-outline-secondary w-100 mt-3">
                <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách giao nhận
            </a>
        </x-admin.detail.panel>
    </div>
</div>
@endsection
