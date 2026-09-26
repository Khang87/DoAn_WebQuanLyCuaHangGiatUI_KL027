@extends('layouts.app')

@section('title', 'Chi tiết danh mục dịch vụ - Sky Laundry')
@section('page-title', 'Chi tiết danh mục dịch vụ')

@section('content')
<x-admin.detail.page-header
    title="Danh mục {{ $category->name }}"
    :back="route('service-categories.index')"
    :subtitle="$category->slug"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$category->status" :enum="\App\Enums\RecordStatus::class" />
        @if($category->deleted_at)
            <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill">
                <i class="fas fa-ban me-1"></i>Đã xóa
            </span>
        @endif
    </x-slot:badge>

    <x-slot:actions>
        <a href="{{ route('service-categories.edit', $category->id) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Chỉnh sửa
        </a>
    </x-slot:actions>
</x-admin.detail.page-header>

@if($category->deleted_at)
    <x-admin.detail.locked text="Danh mục đã bị xóa mềm nên không thể sửa hoặc xóa. Hãy khôi phục nếu cần." />
@endif

<div class="row g-4">
    {{-- ============ CỘT CHÍNH (8/12) ============ --}}
    <div class="col-lg-8">
        <x-admin.detail.panel
            title="Thông tin danh mục"
            :icon="$category->icon ?: 'bi-folder'"
            :iconClass="'bg-primary-subtle text-primary'"
        >
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Tên danh mục" :value="$category->name" />
                <x-admin.detail.info-item label="Mã danh mục" :value="$category->code" />
                <x-admin.detail.info-item label="Slug" :value="$category->slug" />
                <x-admin.detail.info-item label="Số dịch vụ" :value="$category->services->count()" />
                <x-admin.detail.info-item label="Ngày tạo" :value="$category->created_at?->format('d/m/Y')" />
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$category->status" :enum="\App\Enums\RecordStatus::class" :pill="false" />
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>

            <div class="mt-4">
                <div class="detail-field__label mb-2">Mô tả</div>
                <div class="detail-text">{{ $category->description ?: 'Chưa có mô tả.' }}</div>
            </div>
        </x-admin.detail.panel>

        <x-admin.detail.panel title="Dịch vụ trong danh mục" icon="bi-list-check" :iconClass="'bg-secondary-subtle text-secondary'" flush>
            <x-slot:header>
                <span class="text-muted small">{{ $services->total() }} dịch vụ</span>
            </x-slot:header>

            @if($services->isEmpty())
                <x-admin.detail.empty message="Danh mục này chưa có dịch vụ nào" icon="bi-bag" />
            @else
                <div class="table-responsive">
                    <table class="table table-hover detail-table">
                        <thead>
                            <tr>
                                <th>Tên dịch vụ</th>
                                <th>Loại</th>
                                <th class="text-end">Đơn giá</th>
                                <th class="text-end">Đơn vị</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($services as $service)
                                <tr>
                                    <td>
                                        <span class="fw-semibold">
                                            {{ $service->name }}
                                        </span>
                                    </td>
                                    <td>{{ $service->type ?: '—' }}</td>
                                    <td class="text-end"><x-admin.detail.money :value="$service->price" /></td>
                                    <td class="text-end">{{ $service->unit ?: 'kg' }}</td>
                                    <td>
                                        <x-admin.status-badge :status="$service->status" :enum="\App\Enums\RecordStatus::class" size="px-2 py-1" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($services->hasPages())
                    <div class="p-3">{{ $services->links() }}</div>
                @endif
            @endif
        </x-admin.detail.panel>
    </div>

    {{-- ============ CỘT PHỤ (4/12) ============ --}}
    <div class="col-lg-4">
        <x-admin.detail.panel title="Thao tác" icon="bi-sliders" :iconClass="'bg-secondary-subtle text-secondary'">
            <div class="d-grid gap-2">
                <a href="{{ route('service-categories.edit', $category->id) }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-pencil me-1"></i>Chỉnh sửa
                </a>
                <a href="{{ route('services.create') }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>Thêm dịch vụ
                </a>
                <a href="{{ route('service-categories.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách
                </a>
            </div>
        </x-admin.detail.panel>
    </div>
</div>
@endsection
