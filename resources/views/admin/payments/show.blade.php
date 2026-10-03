@extends('layouts.app')

@section('title', 'Chi tiết thanh toán - Sky Laundry')
@section('page-title', 'Chi tiết thanh toán')

@section('content')
@php
    $paymentCode = 'TT' . str_pad((string) $payment->ThanhToanID, 4, '0', STR_PAD_LEFT);
    $isLocked = $payment->isLocked();
    $isOwner = auth()->user()?->isOwner() ?? false;
    $canManageSettled = ! $isLocked || $isOwner;
    $order = $payment->donHang;
    $invoice = $order?->hoaDons?->first();
@endphp

<x-admin.detail.page-header
    title="Thanh toán {{ $paymentCode }}"
    :subtitle="$payment->getMethodLabel() . ' · ' . $payment->ThoiGian?->format('d/m/Y H:i')"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$payment->TrangThai" :enum="\App\Enums\PaymentStatus::class" />
        @if($isLocked)
            <span class="badge {{ $isOwner ? 'bg-success-subtle text-success-emphasis border-success' : 'bg-secondary-subtle text-secondary-emphasis border-secondary' }} border px-3 py-2 rounded-pill">
                <i class="bi {{ $isOwner ? 'bi-shield-check' : 'bi-lock-fill' }} me-1"></i>
                {{ $isOwner ? 'Đã thanh toán · Có thể điều chỉnh' : 'Đã quyết toán' }}
            </span>
        @endif
    </x-slot:badge>
</x-admin.detail.page-header>

@if($isLocked && ! $isOwner)
    <x-admin.detail.locked text="Thanh toán đã quyết toán nên bị khóa sửa/xóa. Liên hệ Chủ cửa hàng nếu cần điều chỉnh." />
@endif

<div class="row g-4 align-items-start">
    {{-- ============ CỘT CHÍNH ============ --}}
    <div class="col-lg-7">
        <x-admin.detail.panel title="Thông tin thanh toán" icon="bi-cash-coin" :iconClass="'bg-primary-subtle text-primary'">
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Mã thanh toán" :value="$paymentCode" />
                <x-admin.detail.info-item label="Số tiền">
                    <x-admin.detail.money :value="$payment->SoTien" class="detail-summary__total text-primary" />
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Phương thức">
                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary px-3 py-2 rounded-pill">
                        <i class="bi {{ $payment->getMethodIcon() }} me-1"></i>{{ $payment->getMethodLabel() }}
                    </span>
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$payment->TrangThai" :enum="\App\Enums\PaymentStatus::class" :pill="false" />
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Mã giao dịch" :value="$payment->MaGiaoDich" />
                <x-admin.detail.info-item label="Ngày thanh toán"
                    :value="$payment->ThoiGian?->format('d/m/Y H:i')"
                />
            </x-admin.detail.info-grid>
        </x-admin.detail.panel>

        <x-admin.detail.panel title="Khách hàng" icon="bi-person" :iconClass="'bg-info-subtle text-info'">
            @if($order?->khachHang)
                <x-admin.detail.info-grid :columns="1">
                    <x-admin.detail.info-item label="Họ và tên" :value="$order->khachHang->HoTen" />
                    <x-admin.detail.info-item label="Số điện thoại" :value="$order->khachHang->SoDienThoai" />
                    <x-admin.detail.info-item label="Địa chỉ" :value="$order->khachHang->DiaChi" />
                </x-admin.detail.info-grid>
                <div class="mt-3">
                    <a href="{{ route('customers.show', $order->khachHang->KhachHangID) }}" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Hồ sơ khách hàng
                    </a>
                </div>
            @else
                <x-admin.detail.empty message="Chưa có thông tin khách hàng" icon="bi-person" />
            @endif
        </x-admin.detail.panel>
    </div>

    {{-- ============ CỘT PHỤ (5/12) ============ --}}
    <div class="col-lg-5 d-flex flex-column">
        <x-admin.detail.panel title="Chứng từ liên kết" icon="bi-link-45deg" :iconClass="'bg-secondary-subtle text-secondary'">
            <x-admin.detail.info-grid :columns="1">
                <x-admin.detail.info-item label="Đơn hàng">
                    @if($order)
                        <a href="{{ route('orders.show', $order->DonHangID) }}" class="text-decoration-none">
                            {{ $order->MaDonHang }}
                        </a>
                    @else
                        <span class="detail-empty-value">—</span>
                    @endif
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Hóa đơn">
                    @if($invoice)
                        <a href="{{ route('invoices.show', $invoice->HoaDonID) }}" class="text-decoration-none">
                            {{ $invoice->MaHoaDon ?: 'Xem hóa đơn' }}
                        </a>
                    @else
                        <span class="detail-empty-value">Chưa liên kết</span>
                    @endif
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>
        </x-admin.detail.panel>

        <div class="card shadow-sm border-0 order-first">
            <div class="card-header bg-transparent border-bottom d-flex align-items-center gap-2 py-3">
                <div class="bg-light rounded p-2 d-inline-flex align-items-center justify-content-center">
                    <i class="fas fa-sliders-h text-secondary"></i>
                </div>
                <h5 class="card-title mb-0 fw-bold">Thao tác</h5>
            </div>
            <div class="card-body d-flex flex-column gap-2">
                @if($canManageSettled)
                    <a href="{{ route('payments.edit', $payment->ThanhToanID) }}" class="btn btn-primary w-100 py-2">
                        <i class="fas fa-pencil-alt me-1"></i> Chỉnh sửa
                    </a>
                @endif

                @if($canManageSettled)
                    @can('payments.delete')
                        <x-admin.detail.confirm-form
                            :action="route('payments.destroy', $payment->ThanhToanID)"
                            title="Xóa phiếu thanh toán?"
                            text="Hành động này không thể hoàn tác."
                            label="Xóa phiếu thanh toán"
                            icon="bi-trash"
                            variant="btn-outline-danger"
                            size="py-2"
                            block
                        />
                    @endcan
                @endif

                <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary w-100 py-2 text-dark">
                    <i class="fas fa-arrow-left me-1"></i> Quay lại danh sách
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
