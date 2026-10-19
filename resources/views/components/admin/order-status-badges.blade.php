@props(['order', 'isPaid', 'isReceiving', 'compact' => false])

@php
    $badgeSize = $compact ? 'px-2 py-1' : 'px-3 py-2';
    $operationalStatus = $order->operationalStatusEnum();
@endphp

@if(! ($isPaid && $operationalStatus === \App\Enums\OrderStatus::Paid))
    <x-admin.status-badge :status="$operationalStatus->value" :enum="\App\Enums\OrderStatus::class" :size="$badgeSize" />
@endif

@if($isPaid)
    <span class="badge bg-success-subtle text-success-emphasis border border-success {{ $badgeSize }} rounded-pill">
        <i class="bi bi-check-circle me-1"></i>Đã thanh toán
    </span>
@endif

@if($isReceiving)
    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning {{ $badgeSize }} rounded-pill">
        <i class="bi bi-clipboard-check me-1" aria-hidden="true"></i>Chờ kiểm tra và tiếp nhận thực tế
    </span>
@endif
