@extends('layouts.app')

@section('title', 'Chi tiết dịch vụ - Sky Laundry')
@section('page-title', 'Chi tiết dịch vụ')

@section('content')
@php
    $iconConfig = $service->iconConfig();
@endphp

<x-admin.detail.page-header
    title="Dịch vụ {{ $service->name }}"
    :back="route('services.index')"
    :subtitle="$service->category?->name"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$service->status" :enum="\App\Enums\RecordStatus::class" />
        @if($service->deleted_at)
            <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill">
                <i class="fas fa-ban me-1"></i>Đã ngưng sử dụng
            </span>
        @endif
        @if($service->processing_time)
            <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning px-3 py-2 ms-2 d-inline-flex align-items-center" style="background-color: #fef3c7 !important; color: #b45309 !important; border-color: #fde68a !important; box-shadow: none !important;">
                <i class="bi bi-clock me-1" style="color: #b45309;"></i>{{ $service->formatted_processing_time }}
            </span>
        @endif
    </x-slot:badge>
</x-admin.detail.page-header>

@if($service->deleted_at)
    <x-admin.detail.locked text="Dịch vụ đã bị xóa mềm nên không thể sửa hoặc xóa. Hãy khôi phục nếu cần." />
@endif

<div class="row g-4">
    {{-- ============ CỘT CHÍNH (8/12) ============ --}}
    <div class="col-lg-8">
        <x-admin.detail.panel
            title="Thông tin dịch vụ"
            :icon="$service->icon ?: $iconConfig['icon']"
            :iconClass="$iconConfig['bg'] . ' text-white'"
        >
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Tên dịch vụ" :value="$service->name" />
                <x-admin.detail.info-item label="Loại dịch vụ" :value="$service->type ?: 'Chưa phân loại'" />
                <x-admin.detail.info-item label="Đơn giá">
                    <x-admin.detail.money :value="$service->price" class="text-primary" />
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Đơn vị tính" :value="$service->unit ?: 'kg'" />
                <x-admin.detail.info-item label="Danh mục" :value="$service->category?->name ?: 'Chưa phân loại'" />
                <x-admin.detail.info-item label="Biểu tượng" :value="$service->icon ?: 'Chưa cài đặt'" />
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$service->status" :enum="\App\Enums\RecordStatus::class" :pill="false" />
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>

            <div class="mt-4">
                <div class="detail-field__label mb-2">Mô tả</div>
                <div class="detail-text">{{ $service->description ?: 'Chưa có mô tả chi tiết.' }}</div>
            </div>
        </x-admin.detail.panel>
    </div>

    {{-- ============ CỘT PHỤ (4/12) ============ --}}
    <div class="col-lg-4">
        <x-admin.detail.panel title="Thao tác" icon="bi-sliders" :iconClass="'bg-secondary-subtle text-secondary'">
            <div class="d-grid gap-2">
                <a href="{{ route('services.edit', $service) }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-pencil me-1"></i>Chỉnh sửa dịch vụ
                </a>
                <x-admin.detail.confirm-form
                    :action="route('services.destroy', $service)"
                    title="Xóa dịch vụ?"
                    text="Hành động này không thể hoàn tác."
                    icon="bi-trash"
                    variant="btn-outline-danger"
                    :block="true"
                >
                    Xóa dịch vụ
                </x-admin.detail.confirm-form>
                <a href="{{ route('services.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách
                </a>
            </div>
        </x-admin.detail.panel>
    </div>
</div>
@endsection
