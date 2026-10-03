@extends('layouts.app')

@section('title', 'Chi tiết phiếu giao nhận - Sky Laundry')
@section('page-title', 'Chi tiết phiếu giao nhận')

@section('content')
@php
    $deliveryCode = $delivery->MaGiaoNhan ?: 'GH' . str_pad($delivery->GiaoNhanID, 4, '0', STR_PAD_LEFT);
@endphp

<x-admin.detail.page-header
    title="Phiếu giao nhận {{ $deliveryCode }}"
    :subtitle="($delivery->HinhThuc === 'nhan_do' ? 'Nhận đồ' : 'Giao đồ') . ' · ' . ($delivery->ThoiGianDuKien?->format('d/m/Y') ?: 'Chưa hẹn ngày')"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$delivery->TrangThai" :enum="\App\Enums\DeliveryStatus::class" />
    </x-slot:badge>
</x-admin.detail.page-header>

<div class="row g-4 align-items-start">
    {{-- ============ CỘT CHÍNH (7/12) ============ --}}
    <div class="col-lg-7">
        <x-admin.detail.panel title="Thông tin giao nhận" icon="bi-truck" :iconClass="'bg-primary-subtle text-primary'">
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Mã phiếu" :value="$deliveryCode" />
                <x-admin.detail.info-item label="Hình thức">
                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary px-3 py-2 rounded-pill">
                        <i class="bi {{ $delivery->HinhThuc === 'nhan_do' ? 'bi-box-arrow-in-down' : 'bi-truck' }} me-1"></i>{{ $delivery->HinhThuc === 'nhan_do' ? 'Nhận đồ' : 'Giao đồ' }}
                    </span>
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Đơn hàng">
                    @if($delivery->donHang)
                        <a href="{{ route('orders.show', $delivery->donHang->DonHangID) }}" class="text-decoration-none">
                            {{ $delivery->donHang->MaDonHang }}
                        </a>
                    @else
                        <span class="detail-empty-value">—</span>
                    @endif
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$delivery->TrangThai" :enum="\App\Enums\DeliveryStatus::class" :pill="false" />
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Ngày giao nhận" :value="$delivery->ThoiGianDuKien?->format('d/m/Y')" />
                <x-admin.detail.info-item label="Giờ giao nhận" :value="$delivery->ThoiGianDuKien?->format('H:i')" />
                <x-admin.detail.info-item label="Nhân viên phụ trách" :value="$delivery->nhanVien?->HoTen ?: 'Chưa phân công'" />
                <x-admin.detail.info-item label="Ngày tạo" :value="$delivery->ThoiGianDuKien?->format('d/m/Y H:i')" />
            </x-admin.detail.info-grid>

            <div class="mt-4">
                <div class="detail-field__label mb-2">Địa chỉ</div>
                <div class="detail-text">{{ $delivery->DiaChi ?: 'Chưa cập nhật địa chỉ.' }}</div>
            </div>

            @if($delivery->GhiChu)
                <div class="mt-3">
                    <div class="detail-field__label mb-2">Ghi chú</div>
                    <div class="detail-text">{{ $delivery->GhiChu }}</div>
                </div>
            @endif
        </x-admin.detail.panel>
    </div>

    {{-- ============ CỘT PHỤ (5/12) ============ --}}
    <div class="col-lg-5 d-flex flex-column">
        <div class="card shadow-sm border-0 order-first">
            <div class="card-header bg-transparent border-bottom d-flex align-items-center gap-2 py-3">
                <div class="bg-light rounded p-2 d-inline-flex align-items-center justify-content-center">
                    <i class="fas fa-sliders-h text-secondary"></i>
                </div>
                <h5 class="card-title mb-0 fw-bold">Thao tác</h5>
            </div>
            <div class="card-body d-flex flex-column gap-2">
                <a href="{{ route('deliveries.edit', $delivery) }}" class="btn btn-primary w-100 py-2">
                    <i class="fas fa-pencil-alt me-1"></i> Chỉnh sửa
                </a>

                @can('deliveries.delete')
                    <x-admin.detail.confirm-form
                        :action="route('deliveries.destroy', $delivery)"
                        title="Xóa phiếu giao nhận?"
                        text="Hành động này không thể hoàn tác."
                        label="Xóa phiếu giao nhận"
                        icon="bi-trash"
                        variant="btn-outline-danger"
                        size="py-2"
                        block
                    />
                @endcan

                <a href="{{ route('deliveries.index') }}" class="btn btn-outline-secondary w-100 py-2 text-dark">
                    <i class="fas fa-arrow-left me-1"></i> Quay lại danh sách
                </a>
            </div>
        </div>

        <x-admin.detail.panel title="Khách hàng" icon="bi-person" :iconClass="'bg-secondary-subtle text-secondary'">
            @php
                $deliveryCustomer = $delivery->donHang?->khachHang;
            @endphp

            @if($deliveryCustomer)
                <x-admin.detail.info-grid :columns="1">
                    <x-admin.detail.info-item label="Họ và tên" :value="$deliveryCustomer->HoTen" />
                    <x-admin.detail.info-item label="Số điện thoại" :value="$deliveryCustomer->SoDienThoai" />
                    <x-admin.detail.info-item label="Địa chỉ" :value="$deliveryCustomer->DiaChi" />
                </x-admin.detail.info-grid>
                <div class="mt-3">
                    <a href="{{ route('customers.show', $deliveryCustomer->KhachHangID) }}" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Hồ sơ khách hàng
                    </a>
                </div>
            @else
                <x-admin.detail.empty message="Phiếu giao nhận chưa gắn khách hàng" icon="bi-person" />
            @endif
        </x-admin.detail.panel>
    </div>
</div>
@endsection
