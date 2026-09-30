@extends('layouts.app')

@section('title', 'Chi tiết dịch vụ - Sky Laundry')
@section('page-title', 'Chi tiết dịch vụ')

@section('content')
@php
    $serviceIcon = $service->icon();
@endphp

<x-admin.detail.page-header
    title="Dịch vụ {{ $service->TenDichVu }}"
    :subtitle="$service->loaiDichVu?->TenLoaiDichVu"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$service->statusLabel" :enum="\App\Enums\RecordStatus::class" />
    </x-slot:badge>
</x-admin.detail.page-header>

<div class="row g-4">
    {{-- ============ CỘT CHÍNH (8/12) ============ --}}
    <div class="col-lg-8">
        <x-admin.detail.panel
            title="Thông tin dịch vụ"
            :icon="$serviceIcon"
            :iconClass="'bg-primary-subtle text-primary'"
        >
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Tên dịch vụ" :value="$service->TenDichVu" />
                <x-admin.detail.info-item label="Danh mục" :value="$service->loaiDichVu?->TenLoaiDichVu ?: 'Chưa phân loại'" />
                <x-admin.detail.info-item label="Thời gian ước tính (phút)">
                    <strong class="text-primary">{{ $service->ThoiGianDuKien ?? '—' }}</strong>
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$service->statusLabel" :enum="\App\Enums\RecordStatus::class" :pill="false" />
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>

            <div class="mt-4">
                <div class="detail-field__label mb-2">Mô tả dịch vụ</div>
                <div class="detail-text">{{ $service->MoTa ?: 'Chưa có mô tả.' }}</div>
            </div>
        </x-admin.detail.panel>

        <x-admin.detail.panel title="Bảng giá liên quan" icon="bi-currency-dollar" :iconClass="'bg-success-subtle text-success'" flush>
            <x-slot:header>
                <a href="{{ route('pricings.create') }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus-lg me-1"></i>Thêm bảng giá
                </a>
            </x-slot:header>

            @if($service->bangGias->isEmpty())
                <x-admin.detail.empty message="Chưa có bảng giá nào cho dịch vụ này" icon="bi-currency-dollar" />
            @else
                <div class="table-responsive">
                    <table class="table table-hover detail-table">
                        <thead>
                            <tr>
                                <th>Loại đồ giặt</th>
                                <th>Đơn vị tính</th>
                                <th>Đơn giá</th>
                                <th>Áp dụng từ</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($service->bangGias as $pricing)
                                <tr>
                                    <td class="fw-semibold">{{ $pricing->loaiDoGiat?->TenLoaiDoGiat ?? '—' }}</td>
                                    <td>{{ $pricing->donViTinh?->TenDonViTinh ?? '—' }}</td>
                                    <td class="fw-semibold text-primary">{{ number_format($pricing->DonGia) }} VNĐ</td>
                                    <td>{{ $pricing->NgayApDung?->format('d/m/Y') }}</td>
                                    <td>
                                        <x-admin.status-badge :status="$pricing->TrangThai" :enum="\App\Enums\RecordStatus::class" :pill="true" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($service->bangGias->hasPages())
                    <div class="p-3">{{ $service->bangGias->links() }}</div>
                @endif
            @endif
        </x-admin.detail.panel>
    </div>

    {{-- ============ CỘT PHỤ (4/12) ============ --}}
    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent border-bottom d-flex align-items-center gap-2 py-3">
                <div class="bg-light rounded p-2 d-inline-flex align-items-center justify-content-center">
                    <i class="fas fa-sliders-h text-secondary"></i>
                </div>
                <h5 class="card-title mb-0 fw-bold">Thao tác</h5>
            </div>
            <div class="card-body d-flex flex-column gap-2">
                <a href="{{ route('garments.edit', $service->DichVuID) }}" class="btn btn-primary w-100 py-2">
                    <i class="fas fa-pencil-alt me-1"></i> Chỉnh sửa
                </a>

                <x-admin.detail.confirm-form
                    :action="route('garments.destroy', $service->DichVuID)"
                    title="Xóa dịch vụ?"
                    text="Hành động này không thể hoàn tác."
                    icon="bi-trash"
                    variant="btn-outline-danger"
                    :block="true"
                >
                    Xóa dịch vụ
                </x-admin.detail.confirm-form>

                <a href="{{ route('garments.index') }}" class="btn btn-outline-secondary w-100 py-2 text-dark">
                    <i class="fas fa-arrow-left me-1"></i> Quay lại danh sách
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
