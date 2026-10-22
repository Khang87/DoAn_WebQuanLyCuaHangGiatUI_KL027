@props([
    'title',
    'subtitle' => null,
    'backUrl' => null,
])

{{--
    Thanh tiêu đề chuẩn của MỌI trang chi tiết trong Admin Panel.

    Tiêu đề + badge trạng thái (slot `badge`) + mô tả phụ, kèm nút "Quay lại"
    góc phải trên cùng khi truyền `backUrl`. Các hành động khác (Chỉnh sửa,
    Xóa, In, ...) vẫn nằm trong Card "Thao tác" ở cột phụ.

    Cách dùng:
        <x-admin.detail.page-header
            title="Chi tiết đơn hàng #DH-001"
            :subtitle="$order->customer?->name"
            backUrl="{{ route('orders.index') }}">
            <x-slot:badge>
                <x-admin.status-badge :status="$order->status" :enum="\App\Enums\OrderStatus::class" />
            </x-slot:badge>
        </x-admin.detail.page-header>
--}}
<div class="detail-header mb-4 d-flex justify-content-between align-items-start gap-3 flex-wrap">
    <div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <h4 class="detail-header__title fw-bold mb-0">{{ $title }}</h4>
            @isset($badge){{ $badge }}@endisset
        </div>
        @if($subtitle)
            <div class="detail-header__subtitle">{{ $subtitle }}</div>
        @endif
    </div>
    @if($backUrl)
        <a href="{{ $backUrl }}" class="btn btn-outline-secondary btn-sm flex-shrink-0">
            <i class="bi bi-arrow-left me-1"></i>Quay lại
        </a>
    @endif
</div>
