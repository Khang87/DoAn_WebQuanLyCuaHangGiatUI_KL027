@props(['order', 'isPaid', 'isReceiving'])

@if(! ($isPaid && $order->statusEnum() === \App\Enums\OrderStatus::Paid))
    <x-admin.status-badge :status="$order->status" :enum="\App\Enums\OrderStatus::class" />
@endif

@if($isPaid)
    <span class="badge bg-success-subtle text-success-emphasis border border-success px-3 py-2 rounded-pill">
        <i class="bi bi-check-circle me-1"></i>Đã thanh toán
    </span>
@endif

@if($isReceiving)
    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-3 py-2 rounded-pill">
        <i class="bi bi-clipboard-check me-1" aria-hidden="true"></i>Chờ kiểm tra và tiếp nhận thực tế
    </span>
@endif
