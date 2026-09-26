@extends('layouts.app')

@section('title', 'Chi tiết mặt hàng - Sky Laundry')
@section('page-title', 'Chi tiết mặt hàng')

@section('content')
@php
    $isLocked = (bool) ($item->order?->isLocked);
@endphp

<x-admin.detail.page-header
    title="Chi tiết mặt hàng"
    :back="route('order-items.index')"
    :subtitle="$item->item_name"
>
    <x-slot:badge>
        @if($isLocked)
            <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary px-3 py-2 rounded-pill">
                <i class="bi bi-lock-fill me-1"></i>Đã quyết toán
            </span>
        @endif
    </x-slot:badge>

    <x-slot:actions>
        @unless($isLocked)
            <a href="{{ route('order-items.edit', $item) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-pencil me-1"></i>Chỉnh sửa
            </a>

            <x-admin.detail.confirm-form
                :action="route('order-items.destroy', $item)"
                title="Xóa chi tiết đơn hàng?"
                text="Hành động này không thể hoàn tác."
                label="Xóa"
                icon="bi-trash"
                variant="btn-outline-danger"
            />
        @endunless
    </x-slot:actions>
</x-admin.detail.page-header>

@if($isLocked)
    <x-admin.detail.locked text="Đơn hàng chứa mặt hàng này đã quyết toán nên không thể sửa hoặc xóa." />
@endif

<div class="row g-4">
    {{-- ============ CỘT CHÍNH (8/12) ============ --}}
    <div class="col-lg-8">
        <x-admin.detail.panel title="Thông tin mặt hàng" icon="bi-bag" :iconClass="'bg-primary-subtle text-primary'">
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Tên mặt hàng" :value="$item->item_name" />
                <x-admin.detail.info-item label="Loại đồ" :value="$item->item_type ?: '—'" />
                <x-admin.detail.info-item label="Đơn giá">
                    <x-admin.detail.money :value="$item->price" />
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Số lượng" :value="number_format($item->quantity)" />
                <x-admin.detail.info-item label="Khối lượng (kg)"
                    :value="$item->weight !== null ? format_weight($item->weight) : null"
                />
                <x-admin.detail.info-item label="Thành tiền">
                    <x-admin.detail.money :value="$item->subtotal" class="text-primary fw-bold" />
                </x-admin.detail.info-item>
            </x-admin.detail.info-grid>

            @if($item->notes)
                <div class="mt-4">
                    <div class="detail-field__label mb-2">Ghi chú</div>
                    <div class="detail-text">{{ $item->notes }}</div>
                </div>
            @endif
        </x-admin.detail.panel>
    </div>

    {{-- ============ CỘT PHỤ (4/12) ============ --}}
    <div class="col-lg-4">
        <x-admin.detail.panel title="Đơn hàng" icon="bi-receipt" :iconClass="'bg-secondary-subtle text-secondary'">
            @if($item->order)
                <x-admin.detail.info-grid :columns="1">
                    <x-admin.detail.info-item label="Mã đơn hàng">
                        <a href="{{ route('orders.show', $item->order->id) }}" class="text-decoration-none">
                            {{ $item->order->code }}
                        </a>
                    </x-admin.detail.info-item>
                    <x-admin.detail.info-item label="Trạng thái đơn">
                        <x-admin.status-badge :status="$item->order->status" :enum="\App\Enums\OrderStatus::class" :pill="false" />
                    </x-admin.detail.info-item>
                    <x-admin.detail.info-item label="Tổng thanh toán">
                        <x-admin.detail.money :value="$item->order->total_amount" class="fw-bold" />
                    </x-admin.detail.info-item>
                    <x-admin.detail.info-item label="Ngày tạo" :value="$item->order->created_at?->format('d/m/Y H:i')" />
                </x-admin.detail.info-grid>
            @else
                <x-admin.detail.empty message="Mặt hàng không thuộc đơn hàng nào" icon="bi-receipt" />
            @endif
        </x-admin.detail.panel>

        <x-admin.detail.panel title="Dịch vụ & loại đồ" icon="bi-tag" :iconClass="'bg-info-subtle text-info'">
            <x-admin.detail.info-grid :columns="1">
                <x-admin.detail.info-item label="Dịch vụ" :value="$item->service?->name ?: '—'" />
                <x-admin.detail.info-item label="Loại đồ giặt" :value="$item->garment?->name ?: '—'" />
            </x-admin.detail.info-grid>

            @if($item->service)
                <div class="mt-3">
                    <a href="{{ route('services.show', $item->service) }}" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Xem dịch vụ
                    </a>
                </div>
            @endif
        </x-admin.detail.panel>
    </div>
</div>
@endsection
