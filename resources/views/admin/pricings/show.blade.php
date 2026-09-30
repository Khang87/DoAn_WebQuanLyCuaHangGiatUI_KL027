@extends('layouts.app')

@section('title', 'Chi tiết bảng giá - Sky Laundry')
@section('page-title', 'Chi tiết bảng giá')

@section('content')
@php
    $pricingName = $pricing->dichVu?->TenDichVu ?: 'Bảng giá dịch vụ';
@endphp

<x-admin.detail.page-header
    title="Bảng giá {{ $pricingName }}"
    :subtitle="$pricing->NgayApDung?->format('d/m/Y')"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$pricing->TrangThai" :enum="\App\Enums\RecordStatus::class" />
    </x-slot:badge>
</x-admin.detail.page-header>

<div class="row g-4">
    {{-- ============ CỘT CHÍNH (8/12) ============ --}}
    <div class="col-lg-8">
        <x-admin.detail.panel title="Thông tin bảng giá" icon="bi-tags" :iconClass="'bg-primary-subtle text-primary'">
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Dịch vụ" :value="$pricing->dichVu?->TenDichVu ?: '—'" />
                <x-admin.detail.info-item label="Loại đồ giặt" :value="$pricing->loaiDoGiat?->TenLoaiDoGiat ?: '—'" />
                <x-admin.detail.info-item label="Đơn vị tính" :value="$pricing->donViTinh?->KyHieu ?? $pricing->donViTinh?->TenDonViTinh ?: 'kg'" />
                <x-admin.detail.info-item label="Ngày áp dụng" :value="$pricing->NgayApDung?->format('d/m/Y') ?: '—'" />
                <x-admin.detail.info-item label="Ngày tạo" :value="$pricing->NgayTao?->format('d/m/Y H:i')" />
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$pricing->TrangThai" :enum="\App\Enums\RecordStatus::class" :pill="false" />
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>

            <div class="mt-4 text-center bg-light rounded-3 p-4">
                <div class="detail-field__label">Đơn giá</div>
                <div class="display-6 fw-bold text-primary">
                    <x-admin.detail.money :value="$pricing->DonGia" unit="" />
                </div>
                <div class="text-muted">/ {{ $pricing->donViTinh?->KyHieu ?? $pricing->donViTinh?->TenDonViTinh ?: 'kg' }}</div>
            </div>

            @if($pricing->MoTa ?? false)
                <div class="mt-4">
                    <div class="detail-field__label mb-2">Mô tả</div>
                    <div class="detail-text">{{ $pricing->MoTa }}</div>
                </div>
            @endif
        </x-admin.detail.panel>
    </div>

    {{-- ============ CỘT PHỤ (4/12) ============ --}}
    <div class="col-lg-4">
        <x-admin.detail.panel title="Dịch vụ liên quan" icon="bi-box" :iconClass="'bg-secondary-subtle text-secondary'">
            @if($pricing->dichVu)
                <x-admin.detail.info-grid :columns="1">
                    <x-admin.detail.info-item label="Dịch vụ" :value="$pricing->dichVu->TenDichVu" />
                    <x-admin.detail.info-item label="Đơn giá dịch vụ">
                        <x-admin.detail.money :value="$pricing->dichVu->DonGia" />
                    </x-admin.detail.info-item>
                    <x-admin.detail.info-item label="Danh mục" :value="$pricing->dichVu->loaiDichVu?->TenLoaiDichVu" />
                </x-admin.detail.info-grid>
                <div class="mt-3">
                    <a href="{{ route('services.show', $pricing->dichVu) }}" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Xem dịch vụ
                    </a>
                </div>
            @else
                <x-admin.detail.empty message="Bảng giá không gắn với dịch vụ nào" icon="bi-box" />
            @endif
        </x-admin.detail.panel>

        <div class="card shadow-sm border-0 mt-3">
            <div class="card-header bg-transparent border-bottom d-flex align-items-center gap-2 py-3">
                <div class="bg-light rounded p-2 d-inline-flex align-items-center justify-content-center">
                    <i class="fas fa-sliders-h text-secondary"></i>
                </div>
                <h5 class="card-title mb-0 fw-bold">Thao tác</h5>
            </div>
            <div class="card-body d-flex flex-column gap-2">
                <a href="{{ route('pricings.edit', $pricing) }}" class="btn btn-primary w-100 py-2">
                    <i class="fas fa-pencil-alt me-1"></i> Chỉnh sửa
                </a>

                <a href="{{ route('pricings.index') }}" class="btn btn-outline-secondary w-100 py-2 text-dark">
                    <i class="fas fa-arrow-left me-1"></i> Quay lại danh sách
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
