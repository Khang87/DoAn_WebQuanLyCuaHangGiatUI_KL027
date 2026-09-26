@extends('layouts.app')

@section('title', 'Chi tiết mã giảm giá - Sky Laundry')
@section('page-title', 'Chi tiết mã giảm giá')

@section('content')
@php
    $discountLabels = [
        'percent' => 'Phần trăm',
        'fixed' => 'Số tiền cố định',
        'free_shipping' => 'Miễn phí giao hàng',
    ];
    $discountBadge = [
        'percent' => 'bg-primary-subtle text-primary-emphasis border border-primary',
        'fixed' => 'bg-purple-subtle text-purple-emphasis border border-purple',
        'free_shipping' => 'bg-warning-subtle text-warning-emphasis border border-warning',
    ];
    $discountType = $coupon->discount_type;
    $usedPercent = $coupon->max_uses ? min(100, ($coupon->used_count / $coupon->max_uses) * 100) : 0;
@endphp

<x-admin.detail.page-header
    title="Mã giảm giá {{ $coupon->code }}"
    :back="route('coupons.index')"
    :subtitle="$coupon->promotion?->name"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$coupon->status" :enum="\App\Enums\RecordStatus::class" />
    </x-slot:badge>

    <x-slot:actions>
        <a href="{{ route('coupons.edit', $coupon) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Chỉnh sửa
        </a>

        <x-admin.detail.confirm-form
            :action="route('coupons.destroy', $coupon)"
            title="Xóa mã giảm giá?"
            text="Hành động này không thể hoàn tác."
            label="Xóa"
            icon="bi-trash"
            variant="btn-outline-danger"
        />
    </x-slot:actions>
</x-admin.detail.page-header>

<div class="row g-4">
    {{-- ============ CỘT CHÍNH (8/12) ============ --}}
    <div class="col-lg-8">
        <x-admin.detail.panel title="Thông tin mã giảm giá" icon="bi-ticket-perforated" :iconClass="'bg-primary-subtle text-primary'">
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Mã coupon">
                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary px-3 py-2 rounded-pill">
                        {{ $coupon->code }}
                    </span>
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Chương trình">
                    @if($coupon->promotion)
                        <a href="{{ route('promotions.show', $coupon->promotion) }}" class="text-decoration-none">
                            {{ $coupon->promotion->name }} ({{ $coupon->promotion->code }})
                        </a>
                    @else
                        <span class="detail-empty-value">Không thuộc chương trình nào</span>
                    @endif
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Loại giảm">
                    <span class="badge {{ $discountBadge[$discountType] ?? 'bg-secondary-subtle text-secondary-emphasis border border-secondary' }} px-3 py-2 rounded-pill">
                        {{ $discountLabels[$discountType] ?? 'Không xác định' }}
                    </span>
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Giá trị">
                    @if($discountType === 'percent')
                        {{ rtrim(rtrim((string) $coupon->discount_value, '0'), '.') }}%
                    @elseif($discountType === 'fixed')
                        <x-admin.detail.money :value="$coupon->discount_value" class="text-primary fw-bold" />
                    @else
                        <span class="detail-empty-value">Miễn phí</span>
                    @endif
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Số lần dùng" :value="$coupon->used_count . '/' . ($coupon->max_uses ?: '∞')" />
                <x-admin.detail.info-item label="Ngày hết hạn" :value="$coupon->expires_at?->format('d/m/Y') ?: 'Không thời hạn'" />
                <x-admin.detail.info-item label="Ngày tạo" :value="$coupon->created_at?->format('d/m/Y H:i')" />
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$coupon->status" :enum="\App\Enums\RecordStatus::class" :pill="false" />
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>
        </x-admin.detail.panel>
    </div>

    {{-- ============ CỘT PHỤ (4/12) ============ --}}
    <div class="col-lg-4">
        <x-admin.detail.panel title="Mức độ sử dụng" icon="bi-bar-chart" :iconClass="'bg-success-subtle text-success'">
            <div class="d-flex justify-content-between align-items-baseline mb-2">
                <span class="detail-field__label">Đã dùng</span>
                <span class="detail-summary__total">{{ number_format($usedPercent, 1) }}%</span>
            </div>
            <div class="progress" style="height: 20px;">
                <div class="progress-bar {{ $usedPercent >= 100 ? 'bg-danger' : 'bg-success' }}" role="progressbar"
                     style="width: {{ $usedPercent }}%" aria-valuenow="{{ $usedPercent }}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <div class="detail-field__label mt-3 mb-2">Hạn mức</div>
            <x-admin.detail.info-grid :columns="1">
                <x-admin.detail.info-item label="Đã sử dụng" :value="$coupon->used_count" />
                <x-admin.detail.info-item label="Tối đa" :value="$coupon->max_uses ?: 'Không giới hạn'" />
            </x-admin.detail.info-grid>
        </x-admin.detail.panel>
    </div>
</div>
@endsection
