@extends('layouts.app')

@section('title', 'Chi tiết đặt lịch - Sky Laundry')
@section('page-title', 'Chi tiết đặt lịch')

@section('content')
@php
    $bookingCode = $booking->code ?: 'BK' . str_pad($booking->id, 4, '0', STR_PAD_LEFT);
@endphp

<x-admin.detail.page-header
    title="Lịch hẹn {{ $bookingCode }}"
    :back="route('bookings.index')"
    :subtitle="$booking->method_label . ' ngày ' . ($booking->scheduled_date?->format('d/m/Y') ?: '—')"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$booking->status" :enum="\App\Enums\BookingStatus::class" />
    </x-slot:badge>

    <x-slot:actions>
        <a href="{{ route('bookings.edit', $booking) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Chỉnh sửa
        </a>

        @if($booking->order)
            <a href="{{ route('orders.show', $booking->order) }}" class="btn btn-outline-info btn-sm">
                <i class="bi bi-receipt me-1"></i>Xem đơn {{ $booking->order->code }}
            </a>
        @elseif($booking->status === 'pending')
            <span class="btn btn-outline-secondary btn-sm disabled"
                  title="Đơn hàng sẽ tự động được tạo khi lịch chuyển sang trạng thái Đã xác nhận">
                <i class="bi bi-hourglass-split me-1"></i>Chờ xác nhận
            </span>
        @elseif($booking->status === 'confirmed')
            <x-admin.detail.confirm-form
                :action="route('bookings.confirm', $booking)"
                method="POST"
                title="Tạo đơn hàng từ lịch hẹn?"
                text="Lịch hẹn này sẽ được chuyển thành đơn hàng."
                label="Tạo đơn hàng"
                icon="bi-cart-plus"
                variant="btn-success"
                color="#16a34a"
                :iconName="'question'"
            />
        @endif
    </x-slot:actions>
</x-admin.detail.page-header>

<div class="row g-4">
    {{-- ============ CỘT CHÍNH (8/12) ============ --}}
    <div class="col-lg-8">
        <x-admin.detail.panel title="Thông tin lịch hẹn" icon="bi-calendar-check" :iconClass="'bg-primary-subtle text-primary'">
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Mã tham chiếu">
                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary px-3 py-2 rounded-pill">
                        {{ $bookingCode }}
                    </span>
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$booking->status" :enum="\App\Enums\BookingStatus::class" :pill="false" />
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Hình thức">
                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary px-3 py-2 rounded-pill">
                        <i class="bi {{ $booking->method === 'nhan_do' ? 'bi-box-arrow-in-down' : 'bi-truck' }} me-1"></i>{{ $booking->method_label }}
                    </span>
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Nhân viên phụ trách" :value="$booking->staff?->name ?? 'Chưa phân công'" />
                <x-admin.detail.info-item label="Ngày hẹn" :value="$booking->scheduled_date?->format('d/m/Y')" />
                <x-admin.detail.info-item label="Giờ hẹn" :value="$booking->scheduled_time?->format('H:i')" />
            </x-admin.detail.info-grid>

            @if($booking->notes)
                <div class="mt-4">
                    <div class="detail-field__label mb-2">Ghi chú</div>
                    <div class="detail-text">{{ $booking->notes }}</div>
                </div>
            @endif
        </x-admin.detail.panel>
    </div>

    {{-- ============ CỘT PHỤ (4/12) ============ --}}
    <div class="col-lg-4">
        <x-admin.detail.panel title="Khách hàng" icon="bi-person" :iconClass="'bg-secondary-subtle text-secondary'">
            <x-admin.detail.info-grid :columns="1">
                <x-admin.detail.info-item label="Họ và tên" :value="$booking->customer?->name" />
                <x-admin.detail.info-item label="Số điện thoại" :value="$booking->customer?->phone" />
                <x-admin.detail.info-item label="Ngày tạo" :value="$booking->created_at?->format('d/m/Y H:i')" />
            </x-admin.detail.info-grid>

            @if($booking->customer)
                <div class="mt-3">
                    <a href="{{ route('customers.show', $booking->customer->id) }}" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Hồ sơ khách hàng
                    </a>
                </div>
            @endif
        </x-admin.detail.panel>

        <x-admin.detail.panel title="Đơn hàng liên kết" icon="bi-receipt" :iconClass="'bg-success-subtle text-success'">
            @if($booking->order)
                <x-admin.detail.info-grid :columns="1">
                    <x-admin.detail.info-item label="Mã đơn hàng">
                        <a href="{{ route('orders.show', $booking->order) }}" class="text-decoration-none">
                            {{ $booking->order->code }}
                        </a>
                    </x-admin.detail.info-item>
                    <x-admin.detail.info-item label="Trạng thái đơn">
                        <x-admin.status-badge :status="$booking->order->status" :enum="\App\Enums\OrderStatus::class" :pill="false" />
                    </x-admin.detail.info-item>
                    <x-admin.detail.info-item label="Tổng thanh toán">
                        <x-admin.detail.money :value="$booking->order->total_amount" class="fw-bold" />
                    </x-admin.detail.info-item>
                </x-admin.detail.info-grid>
                <div class="detail-lock mt-3">
                    <i class="bi bi-info-circle"></i>
                    <span>Đơn hàng được tạo tự động khi lịch chuyển sang trạng thái Đã xác nhận.</span>
                </div>
            @else
                <x-admin.detail.empty message="Chưa có đơn — sẽ tự động tạo khi lịch được xác nhận" icon="bi-hourglass-split" />
            @endif
        </x-admin.detail.panel>
    </div>
</div>
@endsection
