@extends('layouts.app')

@section('title', 'Chi tiết mã giảm giá - Sky Laundry')
@section('page-title', 'Chi tiết mã giảm giá')

@section('content')
@php
    $discountLabels = [
        'Phần trăm' => 'Phần trăm',
        'Tiền mặt' => 'Số tiền cố định',
    ];
    $discountBadge = [
        'Phần trăm' => 'bg-primary-subtle text-primary-emphasis border border-primary',
        'Tiền mặt' => 'bg-purple-subtle text-purple-emphasis border border-purple',
    ];
    $discountType = $coupon->LoaiKhuyenMai;
    $usedPercent = $coupon->SoLuongSuDung ? min(100, ($coupon->SoLuongSuDung / 100) * 100) : 0; // Approximate since no max_uses
@endphp

<x-admin.detail.page-header
    title="Mã giảm giá {{ $coupon->code }}"
    :subtitle="$coupon->TenKhuyenMai"
    backUrl="{{ route('coupons.index') }}"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$coupon->statusLabel" :enum="\App\Enums\RecordStatus::class" />
    </x-slot:badge>
</x-admin.detail.page-header>

<div class="row g-4 align-items-start">
    {{-- ============ CỘT CHÍNH (7/12) ============ --}}
    <div class="col-lg-7">
        <x-admin.detail.panel title="Thông tin mã giảm giá" icon="bi-ticket-perforated" :iconClass="'bg-primary-subtle text-primary'">
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Mã coupon">
                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary px-3 py-2 rounded-pill">
                        {{ $coupon->code }}
                    </span>
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Chương trình">
                    <span class="detail-empty-value">Không áp dụng (mã độc lập)</span>
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Loại giảm">
                    <span class="badge {{ $discountBadge[$discountType] ?? 'bg-secondary-subtle text-secondary-emphasis border border-secondary' }} px-3 py-2 rounded-pill">
                        {{ $discountLabels[$discountType] ?? 'Không xác định' }}
                    </span>
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Giá trị">
                    {{ $coupon->discountValueLabel() }}
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Số lần dùng" :value="$coupon->usageLabel()" />
                <x-admin.detail.info-item label="Ngày hết hạn" :value="$coupon->NgayKetThuc?->format('d/m/Y') ?: 'Không thời hạn'" />
                <x-admin.detail.info-item label="Ngày bắt đầu" :value="$coupon->NgayBatDau?->format('d/m/Y') ?: '—'" />
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$coupon->statusLabel" :enum="\App\Enums\RecordStatus::class" :pill="false" />
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>
        </x-admin.detail.panel>
    </div>

    {{-- ============ CỘT PHỤ (5/12) ============ --}}
    <div class="col-lg-5 d-flex flex-column">
        <x-admin.detail.panel title="Mức độ sử dụng" icon="bi-bar-chart" :iconClass="'bg-success-subtle text-success'">
            <div class="d-flex justify-content-between align-items-baseline mb-2">
                <span class="detail-field__label">Đã dùng</span>
                <span class="detail-summary__total">{{ $coupon->remainingCodes() !== null ? number_format(min(100, ($coupon->SoLuongSuDung / max(1, $coupon->SoLuongSuDung + $coupon->remainingCodes())) * 100), 1) : 'N/A' }}%</span>
            </div>
            <div class="progress" style="height: 20px;">
                <div class="progress-bar {{ $coupon->remainingCodes() === 0 ? 'bg-danger' : 'bg-success' }}" role="progressbar"
                     style="width: {{ $coupon->remainingCodes() !== null ? min(100, 100 - ($coupon->remainingCodes() / max(1, $coupon->SoLuongSuDung + $coupon->remainingCodes()) * 100)) : 0 }}%" aria-valuenow="{{ $coupon->remainingCodes() !== null ? min(100, 100 - ($coupon->remainingCodes() / max(1, $coupon->SoLuongSuDung + $coupon->remainingCodes()) * 100)) : 0 }}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <div class="detail-field__label mt-3 mb-2">Hạn mức</div>
            <x-admin.detail.info-grid :columns="1">
                <x-admin.detail.info-item label="Đã sử dụng" :value="$coupon->SoLuongSuDung ?? 0" />
                <x-admin.detail.info-item label="Tối đa" :value="$coupon->remainingCodes() !== null ? ($coupon->SoLuongSuDung + $coupon->remainingCodes()) : 'Không giới hạn'" />
            </x-admin.detail.info-grid>
        </x-admin.detail.panel>
    </div>
</div>
@endsection
