@extends('layouts.app')

@section('title', 'Chi tiết danh mục dịch vụ - Sky Laundry')
@section('page-title', 'Chi tiết danh mục dịch vụ')

@section('content')
<x-admin.detail.page-header
    title="Danh mục {{ $category->TenLoaiDichVu }}"
    :subtitle="$category->MoTa"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$category->TrangThai" :enum="\App\Enums\RecordStatus::class" />
    </x-slot:badge>
</x-admin.detail.page-header>

<div class="row g-4 align-items-start">
    {{-- ============ CỘT CHÍNH (8/12) ============ --}}
    <div class="col-lg-8">
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
                                <th class="text-end">Thời gian dự kiến</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($services as $service)
                                <tr>
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
                <x-admin.detail.info-item label="Mã danh mục" :value="'DV' . str_pad($category->LoaiDichVuID, 4, '0', STR_PAD_LEFT)" />
                <x-admin.detail.info-item label="Số dịch vụ" :value="$category->dichVus()->count()" />
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$category->TrangThai" :enum="\App\Enums\RecordStatus::class" :pill="false" />
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>

            <div class="mt-4">
                <div class="detail-field__label mb-2">Mô tả</div>
                <div class="detail-text">{{ $category->MoTa ?: 'Chưa có mô tả.' }}</div>
            </div>
        </x-admin.detail.panel>
    </div>

    {{-- ============ CỘT PHỤ (4/12) ============ --}}
    <div class="col-lg-4 d-flex flex-column">
        <div class="card shadow-sm border-0 order-first">
            <div class="card-header bg-transparent border-bottom d-flex align-items-center gap-2 py-3">
                <div class="bg-light rounded p-2 d-inline-flex align-items-center justify-content-center">
                    <i class="fas fa-sliders-h text-secondary"></i>
                </div>
                <h5 class="card-title mb-0 fw-bold">Thao tác</h5>
            </div>
            <div class="card-body d-flex flex-column gap-2">
                <a href="{{ route('service-categories.edit', $category->LoaiDichVuID) }}" class="btn btn-primary w-100 py-2">
                    <i class="fas fa-pencil-alt me-1"></i> Chỉnh sửa
                </a>

                @can('services.create')
                    <a href="{{ route('services.create') }}" class="btn btn-outline-primary w-100 py-2">
                        <i class="fas fa-plus me-1"></i> Thêm dịch vụ
                    </a>
                @endcan

                @can('service_categories.delete')
                    <x-admin.detail.confirm-form
                        :action="route('service-categories.destroy', $category->LoaiDichVuID)"
                        title="Xóa danh mục dịch vụ?"
                        text="Danh mục sẽ được chuyển sang trạng thái tạm ngưng."
                        label="Xóa danh mục dịch vụ"
                        icon="bi-trash"
                        variant="btn-outline-danger"
                        size="py-2"
                        block
                    />
                @endcan

                <a href="{{ route('service-categories.index') }}" class="btn btn-outline-secondary w-100 py-2 text-dark">
                    <i class="fas fa-arrow-left me-1"></i> Quay lại danh sách
                </a>
            </div>
        </div>

    </div>
</div>
@endsection
