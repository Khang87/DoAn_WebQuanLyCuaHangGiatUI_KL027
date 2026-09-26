@extends('layouts.app')

@section('title', 'Chi tiết bảng giá - Sky Laundry')
@section('page-title', 'Chi tiết bảng giá')

@section('content')
@php
    $pricingName = $pricing->service?->name ?: $pricing->name ?: 'Bảng giá dịch vụ';
@endphp

<x-admin.detail.page-header
    title="Bảng giá {{ $pricingName }}"
    :back="route('pricings.index')"
    :subtitle="$pricing->effective_date?->format('d/m/Y')"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$pricing->status" :enum="\App\Enums\RecordStatus::class" />
    </x-slot:badge>

    <x-slot:actions>
        <a href="{{ route('pricings.edit', $pricing) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Chỉnh sửa
        </a>
    </x-slot:actions>
</x-admin.detail.page-header>

<div class="row g-4">
    {{-- ============ CỘT CHÍNH (8/12) ============ --}}
    <div class="col-lg-8">
        <x-admin.detail.panel title="Thông tin bảng giá" icon="bi-tags" :iconClass="'bg-primary-subtle text-primary'">
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Dịch vụ" :value="$pricing->service?->name ?: $pricing->name ?: '—'" />
                <x-admin.detail.info-item label="Loại đồ giặt" :value="$pricing->garment?->name ?: $pricing->garment_name ?: '—'" />
                <x-admin.detail.info-item label="Đơn vị tính" :value="$pricing->unit ?: 'kg'" />
                <x-admin.detail.info-item label="Ngày áp dụng" :value="$pricing->effective_date?->format('d/m/Y') ?: $pricing->created_at?->format('d/m/Y')" />
                <x-admin.detail.info-item label="Ngày tạo" :value="$pricing->created_at?->format('d/m/Y H:i')" />
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$pricing->status" :enum="\App\Enums\RecordStatus::class" :pill="false" />
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>

            <div class="mt-4 text-center bg-light rounded-3 p-4">
                <div class="detail-field__label">Đơn giá</div>
                <div class="display-6 fw-bold text-primary">
                    <x-admin.detail.money :value="$pricing->price" unit="" />
                </div>
                <div class="text-muted">/ {{ $pricing->unit ?: 'kg' }}</div>
            </div>

            @if($pricing->description)
                <div class="mt-4">
                    <div class="detail-field__label mb-2">Mô tả</div>
                    <div class="detail-text">{{ $pricing->description }}</div>
                </div>
            @endif
        </x-admin.detail.panel>
    </div>

    {{-- ============ CỘT PHỤ (4/12) ============ --}}
    <div class="col-lg-4">
        <x-admin.detail.panel title="Dịch vụ liên quan" icon="bi-box" :iconClass="'bg-secondary-subtle text-secondary'">
            @if($pricing->service)
                <x-admin.detail.info-grid :columns="1">
                    <x-admin.detail.info-item label="Dịch vụ" :value="$pricing->service->name" />
                    <x-admin.detail.info-item label="Đơn giá dịch vụ">
                        <x-admin.detail.money :value="$pricing->service->price" />
                    </x-admin.detail.info-item>
                    <x-admin.detail.info-item label="Danh mục" :value="$pricing->service->category?->name" />
                </x-admin.detail.info-grid>
                <div class="mt-3">
                    <a href="{{ route('services.show', $pricing->service) }}" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Xem dịch vụ
                    </a>
                </div>
            @else
                <x-admin.detail.empty message="Bảng giá không gắn với dịch vụ nào" icon="bi-box" />
            @endif
        </x-admin.detail.panel>
    </div>
</div>
@endsection
