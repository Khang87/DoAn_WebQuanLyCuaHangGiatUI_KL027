@extends('layouts.app')

@section('title', 'Chi tiết phiếu giao nhận - Sky Laundry')
@section('page-title', 'Chi tiết phiếu giao nhận')

@section('content')
@php
    $deliveryCode = $delivery->code ?: 'GH' . str_pad($delivery->id, 4, '0', STR_PAD_LEFT);
@endphp

<x-admin.detail.page-header
    title="Phiếu giao nhận {{ $deliveryCode }}"
    :back="route('deliveries.index')"
    :subtitle="$delivery->type_label . ' · ' . ($delivery->pickup_date?->format('d/m/Y') ?: 'Chưa hẹn ngày')"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$delivery->status" :enum="\App\Enums\DeliveryStatus::class" />
    </x-slot:badge>

    <x-slot:actions>
        <a href="{{ route('deliveries.edit', $delivery) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Chỉnh sửa
        </a>
    </x-slot:actions>
</x-admin.detail.page-header>

<div class="row g-4">
    {{-- ============ CỘT CHÍNH (8/12) ============ --}}
    <div class="col-lg-8">
        <x-admin.detail.panel title="Thông tin giao nhận" icon="bi-truck" :iconClass="'bg-primary-subtle text-primary'">
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Mã phiếu" :value="$deliveryCode" />
                <x-admin.detail.info-item label="Hình thức">
                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary px-3 py-2 rounded-pill">
                        <i class="bi {{ $delivery->method === 'nhan_do' ? 'bi-box-arrow-in-down' : 'bi-truck' }} me-1"></i>{{ $delivery->type_label }}
                    </span>
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Đơn hàng">
                    @if($delivery->order)
                        <a href="{{ route('orders.show', $delivery->order->id) }}" class="text-decoration-none">
                            {{ $delivery->order->code }}
                        </a>
                    @else
                        <span class="detail-empty-value">—</span>
                    @endif
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$delivery->status" :enum="\App\Enums\DeliveryStatus::class" :pill="false" />
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Ngày giao nhận" :value="$delivery->pickup_date?->format('d/m/Y')" />
                <x-admin.detail.info-item label="Giờ giao nhận" :value="$delivery->pickup_time?->format('H:i')" />
                <x-admin.detail.info-item label="Nhân viên phụ trách" :value="$delivery->employee?->name ?: 'Chưa phân công'" />
                <x-admin.detail.info-item label="Ngày tạo" :value="$delivery->created_at?->format('d/m/Y H:i')" />
            </x-admin.detail.info-grid>

            <div class="mt-4">
                <div class="detail-field__label mb-2">Địa chỉ</div>
                <div class="detail-text">{{ $delivery->address ?: 'Chưa cập nhật địa chỉ.' }}</div>
            </div>

            @if($delivery->notes)
                <div class="mt-3">
                    <div class="detail-field__label mb-2">Ghi chú</div>
                    <div class="detail-text">{{ $delivery->notes }}</div>
                </div>
            @endif
        </x-admin.detail.panel>
    </div>

    {{-- ============ CỘT PHỤ (4/12) ============ --}}
    <div class="col-lg-4">
        <x-admin.detail.panel title="Khách hàng" icon="bi-person" :iconClass="'bg-secondary-subtle text-secondary'">
            @php
                $deliveryCustomer = $delivery->customer ?? $delivery->order?->customer;
            @endphp

            @if($deliveryCustomer)
                <x-admin.detail.info-grid :columns="1">
                    <x-admin.detail.info-item label="Họ và tên" :value="$deliveryCustomer->name" />
                    <x-admin.detail.info-item label="Số điện thoại" :value="$deliveryCustomer->phone" />
                    <x-admin.detail.info-item label="Địa chỉ" :value="$deliveryCustomer->address" />
                </x-admin.detail.info-grid>
                <div class="mt-3">
                    <a href="{{ route('customers.show', $deliveryCustomer->id) }}" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Hồ sơ khách hàng
                    </a>
                </div>
            @else
                <x-admin.detail.empty message="Phiếu giao nhận chưa gắn khách hàng" icon="bi-person" />
            @endif
        </x-admin.detail.panel>
    </div>
</div>
@endsection
