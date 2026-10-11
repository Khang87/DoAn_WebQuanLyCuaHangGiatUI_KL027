@extends('layouts.app')
@section('title', 'Chi tiết lịch sử thanh toán - Sky Laundry')
@section('page-title', 'Lịch sử thanh toán')

@section('content')
<x-admin.detail.page-header :title="'Giao dịch TT'.$payment->getKey()" :subtitle="$payment->donHang?->MaDonHang">
    <x-slot:badge><x-admin.status-badge :status="$payment->TrangThai" :enum="\App\Enums\PaymentStatus::class" /></x-slot:badge>
</x-admin.detail.page-header>
<div class="row g-4">
    <div class="col-lg-8">
        <x-admin.detail.panel title="Thông tin giao dịch" icon="bi-cash-coin">
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Đơn hàng" :value="$payment->donHang?->MaDonHang" />
                <x-admin.detail.info-item label="Khách hàng" :value="$payment->donHang?->khachHang?->HoTen" />
                <x-admin.detail.info-item label="Số tiền"><x-admin.detail.money :value="$payment->SoTien" /></x-admin.detail.info-item>
                <x-admin.detail.info-item label="Phương thức" :value="$payment->getMethodLabel()" />
                <x-admin.detail.info-item label="Mã giao dịch" :value="$payment->MaGiaoDich" />
                <x-admin.detail.info-item label="Thời gian" :value="$payment->ThoiGian?->format('d/m/Y H:i')" />
                <x-admin.detail.info-item label="Người thực hiện" :value="$payment->collectorName()" />
                <x-admin.detail.info-item label="Ghi chú" :value="$payment->GhiChu" />
            </x-admin.detail.info-grid>
        </x-admin.detail.panel>
    </div>
    <div class="col-lg-4">
        <x-admin.detail.panel title="Điều hướng" icon="bi-arrow-return-left">
            <a class="btn btn-outline-secondary w-100" href="{{ route('payment-history.index') }}"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Quay lại lịch sử</a>
            @if($payment->donHang && auth()->user()?->canPermission('orders.view'))
                <a class="btn btn-outline-primary w-100 mt-2" href="{{ route('orders.show', $payment->donHang) }}">Xem đơn hàng</a>
            @endif
        </x-admin.detail.panel>
    </div>
</div>
@endsection
