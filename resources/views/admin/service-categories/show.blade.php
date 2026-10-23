@extends('layouts.app')

@section('title', 'Chi tiết danh mục dịch vụ - Sky Laundry')
@section('page-title', 'Chi tiết danh mục dịch vụ')

@section('content')
<x-admin.detail.page-header
    title="Danh mục {{ $category->TenLoaiDichVu }}"
    :subtitle="$category->MoTa"
    backUrl="{{ route('service-categories.index') }}"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$category->TrangThai" :enum="\App\Enums\RecordStatus::class" />
    </x-slot:badge>
</x-admin.detail.page-header>

<div class="row g-4 align-items-start">
    <div class="col-12">
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
                                <th scope="col">STT</th>
                                <th>Tên dịch vụ</th>
                                <th>Loại</th>
                                <th class="text-end">Thời gian dự kiến</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($services as $service)
                                <tr>
                                    <td>{{ $services->firstItem() + $loop->index }}</td>
                                    <td>
                                        <span class="fw-semibold">
                                            {{ $service->TenDichVu }}
                                        </span>
                                    </td>
                                    <td>{{ $service->loaiDichVu?->TenLoaiDichVu ?: '—' }}</td>
                                    <td class="text-end">{{ $service->ThoiGianDuKien ? $service->ThoiGianDuKien.' phút' : '—' }}</td>
                                    <td>
                                        <x-admin.status-badge :status="$service->TrangThai" :enum="\App\Enums\RecordStatus::class" size="px-2 py-1" />
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

        <x-admin.detail.panel
            title="Thông tin danh mục"
            :icon="'bi-folder'"
            :iconClass="'bg-primary-subtle text-primary'"
        >
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Tên danh mục" :value="$category->TenLoaiDichVu" />
                <x-admin.detail.info-item label="Số dịch vụ" :value="$category->dichVus()->count()" />
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$category->TrangThai" :enum="\App\Enums\RecordStatus::class" />
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>

            <div class="mt-4">
                <div class="detail-field__label mb-2">Mô tả</div>
                <div class="detail-text">{{ $category->MoTa ?: 'Chưa có mô tả.' }}</div>
            </div>
        </x-admin.detail.panel>
    </div>
</div>
@endsection
