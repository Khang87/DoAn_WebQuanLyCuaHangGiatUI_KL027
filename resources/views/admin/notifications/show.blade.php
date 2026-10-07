@extends('layouts.app')

@section('title', 'Chi tiết thông báo - Sky Laundry')
@section('page-title', 'Chi tiết thông báo')

@section('content')
@php
    $typeLabels = [
        'order' => 'Đơn hàng',
        'booking' => 'Lịch hẹn',
        'payment' => 'Thanh toán',
        'promotion' => 'Khuyến mãi',
        'system' => 'Hệ thống',
    ];
    $typeIcons = [
        'order' => 'bi-receipt',
        'booking' => 'bi-calendar-check',
        'payment' => 'bi-cash-coin',
        'promotion' => 'bi-megaphone',
        'system' => 'bi-gear',
    ];
    $type = $notification->LoaiThongBao ?: 'system';
@endphp

<x-admin.detail.page-header
    title="Thông báo #{{ $notification->ThongBaoID }}"
    :subtitle="$notification->ThoiGianGui?->format('d/m/Y H:i')"
>
    <x-slot:badge>
        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary px-3 py-2 rounded-pill">
            <i class="bi {{ $typeIcons[$type] ?? 'bi-bell' }} me-1"></i>{{ $typeLabels[$type] ?? 'Thông báo' }}
        </span>
        <span class="badge {{ $notification->DaDoc ? 'bg-secondary-subtle text-secondary-emphasis border border-secondary' : 'bg-warning-subtle text-warning-emphasis border border-warning fw-bold' }} px-3 py-2 rounded-pill">
            {{ $notification->DaDoc ? 'Đã đọc' : 'Chưa đọc' }}
        </span>
    </x-slot:badge>
</x-admin.detail.page-header>

<div class="row g-4 align-items-start">
    {{-- ============ CỘT CHÍNH (8/12) ============ --}}
    <div class="col-lg-7">
        <x-admin.detail.panel title="Nội dung thông báo" icon="bi-bell" :iconClass="'bg-primary-subtle text-primary'">
            @if($notification->LoaiThongBao === 'internal_password_otp')
                <a class="btn btn-primary mb-3" href="{{ route('internal-password.reset') }}">Đặt lại mật khẩu</a>
            @endif
            <div class="detail-text mb-3">{{ $notification->NoiDung }}</div>
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Loại thông báo" :value="$typeLabels[$type] ?? 'Thông báo'" />
                <x-admin.detail.info-item label="Đơn hàng liên quan">
                    @if($notification->donHang)
                        <a href="{{ route('orders.show', $notification->donHang->DonHangID) }}" class="text-decoration-none">
                            {{ $notification->donHang->MaDonHang }}
                        </a>
                    @else
                        <span class="detail-empty-value">—</span>
                    @endif
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>
        </x-admin.detail.panel>
    </div>

    {{-- ============ CỘT PHỤ (4/12) ============ --}}
    <div class="col-lg-5 d-flex flex-column">
        <x-admin.detail.panel title="Thông tin gửi" icon="bi-clock-history" :iconClass="'bg-secondary-subtle text-secondary'">
            <x-admin.detail.info-grid :columns="1">
                <x-admin.detail.info-item label="Người nhận" :value="$notification->taiKhoan?->HoTen ?? $notification->taiKhoan?->TenDangNhap ?? 'Tất cả nhân viên'" />
                <x-admin.detail.info-item label="Thời điểm gửi" :value="$notification->ThoiGianGui?->format('d/m/Y H:i')" />
                <x-admin.detail.info-item label="Thời điểm đọc" :value="$notification->DaDoc ? $notification->ThoiGianGui?->format('d/m/Y H:i') : '—'" />
            </x-admin.detail.info-grid>
        </x-admin.detail.panel>
    </div>
</div>
@endsection
