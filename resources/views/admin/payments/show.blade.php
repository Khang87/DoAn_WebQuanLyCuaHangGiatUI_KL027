@extends('layouts.app')

@section('title', 'Chi tiết thanh toán - Sky Laundry')
@section('page-title', 'Chi tiết thanh toán')

@section('content')
@php
    $paymentCode = 'TT' . str_pad((string) $payment->id, 4, '0', STR_PAD_LEFT);
    $isLocked = $payment->isLocked();
@endphp

<x-admin.detail.page-header
    title="Thanh toán {{ $paymentCode }}"
    :back="route('payments.index')"
    :subtitle="$payment->getMethodLabel() . ' · ' . ($payment->paid_at?->format('d/m/Y H:i') ?: $payment->created_at?->format('d/m/Y H:i'))"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$payment->status" :enum="\App\Enums\PaymentStatus::class" />
        @if($isLocked)
            <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary px-3 py-2 rounded-pill">
                <i class="bi bi-lock-fill me-1"></i>Đã quyết toán
            </span>
        @endif
    </x-slot:badge>

    <x-slot:actions>
        @if($payment->can_edit)
            <a href="{{ route('payments.edit', $payment->id) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-pencil me-1"></i>Chỉnh sửa
            </a>
        @endif

        @if($payment->can_delete)
            <x-admin.detail.confirm-form
                :action="route('payments.destroy', $payment->id)"
                title="Xóa phiếu thanh toán?"
                text="Hành động này không thể hoàn tác."
                label="Xóa"
                icon="bi-trash"
                variant="btn-outline-danger"
            />
        @endif
    </x-slot:actions>
</x-admin.detail.page-header>

@if($isLocked)
    <x-admin.detail.locked text="Thanh toán đã quyết toán nên bị khóa sửa/xóa. Liên hệ Chủ cửa hàng nếu cần điều chỉnh." />
@endif

<div class="row g-4">
    {{-- ============ CỘT CHÍNH (8/12) ============ --}}
    <div class="col-lg-8">
        <x-admin.detail.panel title="Thông tin thanh toán" icon="bi-cash-coin" :iconClass="'bg-primary-subtle text-primary'">
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Mã thanh toán" :value="$paymentCode" />
                <x-admin.detail.info-item label="Số tiền">
                    <x-admin.detail.money :value="$payment->amount" class="detail-summary__total text-primary" />
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Phương thức">
                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary px-3 py-2 rounded-pill">
                        <i class="bi {{ $payment->getMethodIcon() }} me-1"></i>{{ $payment->getMethodLabel() }}
                    </span>
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$payment->status" :enum="\App\Enums\PaymentStatus::class" :pill="false" />
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Mã giao dịch" :value="$payment->transaction_code" />
                <x-admin.detail.info-item label="Ngày thanh toán"
                    :value="$payment->paid_at?->format('d/m/Y H:i') ?: $payment->created_at?->format('d/m/Y H:i')"
                />
                <x-admin.detail.info-item label="Ngày tạo" :value="$payment->created_at?->format('d/m/Y H:i')" />
                <x-admin.detail.info-item label="Cập nhật lần cuối" :value="$payment->updated_at?->format('d/m/Y H:i')" />
            </x-admin.detail.info-grid>
        </x-admin.detail.panel>
    </div>

    {{-- ============ CỘT PHỤ (4/12) ============ --}}
    <div class="col-lg-4">
        <x-admin.detail.panel title="Chứng từ liên kết" icon="bi-link-45deg" :iconClass="'bg-secondary-subtle text-secondary'">
            <x-admin.detail.info-grid :columns="1">
                <x-admin.detail.info-item label="Đơn hàng">
                    @if($payment->order)
                        <a href="{{ route('orders.show', $payment->order_id) }}" class="text-decoration-none">
                            {{ $payment->order->code }}
                        </a>
                    @else
                        <span class="detail-empty-value">—</span>
                    @endif
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Hóa đơn">
                    @if($payment->invoice)
                        <a href="{{ route('invoices.show', $payment->invoice->id) }}" class="text-decoration-none">
                            {{ $payment->invoice->code ?: 'Xem hóa đơn' }}
                        </a>
                    @else
                        <span class="detail-empty-value">Chưa liên kết</span>
                    @endif
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>
        </x-admin.detail.panel>

        <x-admin.detail.panel title="Khách hàng" icon="bi-person" :iconClass="'bg-info-subtle text-info'">
            @if($payment->order?->customer)
                <x-admin.detail.info-grid :columns="1">
                    <x-admin.detail.info-item label="Họ và tên" :value="$payment->order->customer->name" />
                    <x-admin.detail.info-item label="Số điện thoại" :value="$payment->order->customer->phone" />
                    <x-admin.detail.info-item label="Địa chỉ" :value="$payment->order->customer->address" />
                </x-admin.detail.info-grid>
                <div class="mt-3">
                    <a href="{{ route('customers.show', $payment->order->customer->id) }}" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Hồ sơ khách hàng
                    </a>
                </div>
            @else
                <x-admin.detail.empty message="Chưa có thông tin khách hàng" icon="bi-person" />
            @endif
        </x-admin.detail.panel>
    </div>
</div>
@endsection
