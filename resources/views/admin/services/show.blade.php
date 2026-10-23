@extends('layouts.app')

@section('title', 'Chi tiết dịch vụ - Sky Laundry')
@section('page-title', 'Chi tiết dịch vụ')

@section('content')
<x-admin.detail.page-header
    title="Dịch vụ {{ $service->TenDichVu }}"
    :subtitle="$service->loaiDichVu?->TenLoaiDichVu"
    backUrl="{{ route('services.index') }}"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$service->TrangThai" :enum="\App\Enums\RecordStatus::class" />
    </x-slot:badge>
</x-admin.detail.page-header>

<div class="row g-4 align-items-start">
    <div class="col-lg-8">
        <x-admin.detail.panel title="Bảng giá đang cấu hình" icon="bi bi-tags" iconClass="bg-success text-white" flush>
            @if($service->bangGias->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">STT</th>
                                <th>Loại đồ giặt</th>
                                <th>Đơn vị tính</th>
                                <th>Đơn giá</th>
                                <th>Ngày áp dụng</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($service->bangGias as $pricing)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $pricing->loaiDoGiat?->TenLoaiDoGiat ?: '—' }}</td>
                                    <td>{{ $pricing->donViTinh?->KyHieu ?: $pricing->donViTinh?->TenDonViTinh ?: '—' }}</td>
                                    <td>{{ number_format((float) $pricing->DonGia, 0, ',', '.') }} VNĐ</td>
                                    <td>{{ $pricing->NgayApDung?->format('d/m/Y') ?: '—' }}</td>
                                    <td>
                                        <x-admin.status-badge :status="$pricing->TrangThai" :enum="\App\Enums\RecordStatus::class" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-admin.detail.empty message="Dịch vụ chưa có bảng giá." />
            @endif
        </x-admin.detail.panel>
    </div>

    <div class="col-lg-4 d-flex flex-column">
        <x-admin.detail.panel title="Thông tin dịch vụ" icon="bi bi-water" iconClass="bg-info text-white">
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Tên dịch vụ" :value="$service->TenDichVu" />
                <x-admin.detail.info-item label="Danh mục" :value="$service->loaiDichVu?->TenLoaiDichVu ?: 'Chưa phân loại'" />
                <x-admin.detail.info-item label="Thời gian dự kiến" :value="$service->ThoiGianDuKien ? $service->ThoiGianDuKien . ' phút' : 'Chưa thiết lập'" />
                <x-admin.detail.info-item label="Ngày tạo" :value="$service->NgayTao?->format('d/m/Y H:i') ?: 'Chưa xác định'" />
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$service->TrangThai" :enum="\App\Enums\RecordStatus::class" />
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>

            <div class="mt-4">
                <div class="detail-field__label mb-2">Mô tả</div>
                <div class="detail-text">{{ $service->MoTa ?: 'Chưa có mô tả chi tiết.' }}</div>
            </div>
        </x-admin.detail.panel>
    </div>
</div>
@endsection
