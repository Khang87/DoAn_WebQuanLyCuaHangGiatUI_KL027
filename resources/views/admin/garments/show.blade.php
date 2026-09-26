@extends('layouts.app')

@section('title', 'Chi tiết loại đồ giặt - Sky Laundry')
@section('page-title', 'Chi tiết loại đồ giặt')

@section('content')
@php
    $garmentIcon = $garment->icon();
@endphp

<x-admin.detail.page-header
    title="Loại đồ giặt {{ $garment->name }}"
    :back="route('garments.index')"
    :subtitle="$garment->category_name"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$garment->status" :enum="\App\Enums\RecordStatus::class" />
        @if($garment->deleted_at)
            <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill">
                <i class="fas fa-trash me-1"></i>Đã xóa
            </span>
        @endif
    </x-slot:badge>

    <x-slot:actions>
        <a href="{{ route('garments.edit', $garment->id) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Chỉnh sửa
        </a>
    </x-slot:actions>
</x-admin.detail.page-header>

@if($garment->deleted_at)
    <x-admin.detail.locked text="Loại đồ giặt đã bị xóa mềm nên không thể sửa hoặc xóa. Hãy khôi phục nếu cần." />
@endif

<div class="row g-4">
    {{-- ============ CỘT CHÍNH (8/12) ============ --}}
    <div class="col-lg-8">
        <x-admin.detail.panel
            title="Thông tin loại đồ giặt"
            :icon="$garmentIcon"
            :iconClass="'bg-primary-subtle text-primary'"
        >
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Tên loại đồ" :value="$garment->name" />
                <x-admin.detail.info-item label="Danh mục" :value="$garment->category_name ?: 'Chưa phân loại'" />
                <x-admin.detail.info-item label="Giá dịch vụ">
                    <x-admin.detail.money :value="$garment->price" class="text-primary" />
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$garment->status" :enum="\App\Enums\RecordStatus::class" :pill="false" />
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>

            <div class="mt-4">
                <div class="detail-field__label mb-2">Mô tả hiện trạng trước khi giặt</div>
                <div class="detail-text">{{ $garment->condition_note ?: 'Chưa có thông tin ghi nhận hiện trạng.' }}</div>
            </div>
        </x-admin.detail.panel>

        <x-admin.detail.panel title="Lịch sử hiện trạng" icon="bi-clipboard-check" :iconClass="'bg-info-subtle text-info'" flush>
            <x-slot:header>
                <a href="{{ route('garment-conditions.create') }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus-lg me-1"></i>Thêm
                </a>
            </x-slot:header>

            @if($conditions->isEmpty())
                <x-admin.detail.empty message="Chưa ghi nhận hiện trạng nào cho loại đồ này" icon="bi-clipboard-check" />
            @else
                <div class="table-responsive">
                    <table class="table table-hover detail-table">
                        <thead>
                            <tr>
                                <th>Loại hiện trạng</th>
                                <th>Mô tả</th>
                                <th>Ngày ghi nhận</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($conditions as $condition)
                                <tr>
                                    <td class="fw-semibold">{{ $condition->condition_type }}</td>
                                    <td>{{ $condition->description ?: '—' }}</td>
                                    <td>{{ $condition->created_at?->format('d/m/Y H:i') }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('garment-conditions.show', $condition) }}" class="btn btn-order-action view" title="Xem">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('garment-conditions.edit', $condition) }}" class="btn btn-order-action edit" title="Sửa">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($conditions->hasPages())
                    <div class="p-3">{{ $conditions->links() }}</div>
                @endif
            @endif
        </x-admin.detail.panel>
    </div>

    {{-- ============ CỘT PHỤ (4/12) ============ --}}
    <div class="col-lg-4">
        <x-admin.detail.panel title="Thao tác" icon="bi-sliders" :iconClass="'bg-secondary-subtle text-secondary'">
            <div class="d-grid gap-2">
                <a href="{{ route('garments.edit', $garment->id) }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-pencil me-1"></i>Chỉnh sửa
                </a>
                <x-admin.detail.confirm-form
                    :action="route('garments.destroy', $garment->id)"
                    title="Xóa loại đồ giặt?"
                    text="Hành động này không thể hoàn tác."
                    icon="bi-trash"
                    variant="btn-outline-danger"
                    :block="true"
                >
                    Xóa loại đồ giặt
                </x-admin.detail.confirm-form>
                <a href="{{ route('garments.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách
                </a>
            </div>
        </x-admin.detail.panel>
    </div>
</div>
@endsection
