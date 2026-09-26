@props([
    'title',
    'back',
    'backLabel' => 'Quay lại',
    'subtitle' => null,
])

{{--
    Thanh tiêu đề chuẩn của MỌI trang chi tiết trong Admin Panel.

    Bên trái : tiêu đề + badge trạng thái (slot `badge`)
    Bên phải: nút "Quay lại" + các nút hành động (slot `actions`)

    Cách dùng:
        <x-admin.detail.page-header
            title="Chi tiết đơn hàng #DH-001"
            :back="route('orders.index')"
            :subtitle="$order->customer?->name">
            <x-slot:badge>
                <x-admin.status-badge :status="$order->status" :enum="\App\Enums\OrderStatus::class" />
            </x-slot:badge>
            <x-slot:actions>
                <a href="{{ route('orders.edit', $order) }}" class="btn btn-primary btn-sm">...</a>
            </x-slot:actions>
        </x-admin.detail.page-header>
--}}
<div class="detail-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div class="flex-grow-1">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <h4 class="detail-header__title fw-bold mb-0">{{ $title }}</h4>
            @isset($badge){{ $badge }}@endisset
        </div>
        @if($subtitle)
            <div class="detail-header__subtitle">{{ $subtitle }}</div>
        @endif
    </div>

    <div class="d-flex align-items-center gap-2 flex-wrap">
        @if($back)
            <a href="{{ $back }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>{{ $backLabel }}
            </a>
        @endif
        @isset($actions){{ $actions }}@endisset
    </div>
</div>
