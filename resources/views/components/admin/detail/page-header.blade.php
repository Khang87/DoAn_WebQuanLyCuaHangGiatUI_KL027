@props([
    'title',
    'subtitle' => null,
])

{{--
    Thanh tiêu đề chuẩn của MỌI trang chi tiết trong Admin Panel.

    Chỉ hiển thị tiêu đề + badge trạng thái (slot `badge`) + mô tả phụ.
    Không có nút bấm nào ở góc phải: mọi hành động (Chỉnh sửa, Xóa, In, ...)
    và nút "Quay lại danh sách" đều nằm trong Card "Thao tác" ở cột phụ.

    Cách dùng:
        <x-admin.detail.page-header
            title="Chi tiết đơn hàng #DH-001"
            :subtitle="$order->customer?->name">
            <x-slot:badge>
                <x-admin.status-badge :status="$order->status" :enum="\App\Enums\OrderStatus::class" />
            </x-slot:badge>
        </x-admin.detail.page-header>
--}}
<div class="detail-header mb-4">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <h4 class="detail-header__title fw-bold mb-0">{{ $title }}</h4>
        @isset($badge){{ $badge }}@endisset
    </div>
    @if($subtitle)
        <div class="detail-header__subtitle">{{ $subtitle }}</div>
    @endif
</div>
