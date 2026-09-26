@extends('layouts.app')

@section('title', 'Chi tiết đơn hàng - Sky Laundry')
@section('page-title', 'Chi tiết đơn hàng')

@section('content')
@php
    $isPaid = $order->is_paid;
    $isOwner = auth()->user()?->isOwner();
    $canEdit = (! $isPaid || $isOwner) && $order->can_edit;

    // Tính trước để tránh dấu ">" trong biểu thức thuộc tính của thẻ Blade.
    $hasPromotionDiscount = (float) $order->discount_by_promotion > 0;
    $hasPointsDiscount = (float) $order->discount_by_points > 0;
@endphp

<x-admin.detail.page-header
    title="Đơn hàng {{ $order->code }}"
    :back="route('orders.index')"
    :subtitle="$order->created_at?->format('d/m/Y H:i')"
>
    <x-slot:badge>
        <x-admin.status-badge :status="$order->status" :enum="\App\Enums\OrderStatus::class" />
        @if($order->is_locked)
            <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary px-3 py-2 rounded-pill">
                <i class="bi bi-lock-fill me-1"></i>Đã quyết toán
            </span>
        @endif
    </x-slot:badge>

    <x-slot:actions>
        @if($order->invoice)
            <a href="{{ route('invoices.show', $order->invoice->id) }}" class="btn btn-outline-info btn-sm">
                <i class="fas fa-file-invoice me-1"></i>Xem hóa đơn
            </a>
        @elseif(in_array($order->status, ['completed', 'ready_for_pickup', 'processing']))
            <a href="{{ route('invoices.create', ['order_id' => $order->id]) }}" class="btn btn-outline-info btn-sm">
                <i class="fas fa-file-invoice me-1"></i>Tạo hóa đơn
            </a>
        @endif
        @if($canEdit)
            <a href="{{ route('orders.edit', $order->id) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-pencil me-1"></i>Chỉnh sửa
            </a>
        @endif
    </x-slot:actions>
</x-admin.detail.page-header>

@if($order->is_locked)
    <x-admin.detail.locked text="Đơn hàng đã quyết toán nên bị khóa sửa/xóa. Liên hệ Chủ cửa hàng nếu cần điều chỉnh." />
@endif

<div class="row g-4">
    {{-- ============ CỘT CHÍNH (8/12) ============ --}}
    <div class="col-lg-8">
        <x-admin.detail.panel title="Thông tin đơn hàng" icon="bi-receipt" :iconClass="'bg-primary-subtle text-primary'">
            <x-admin.detail.info-grid :columns="2">
                <x-admin.detail.info-item label="Mã đơn hàng" :value="$order->code" />
                <x-admin.detail.info-item label="Trạng thái">
                    <x-admin.status-badge :status="$order->status" :enum="\App\Enums\OrderStatus::class" :pill="false" />
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Khách hàng">
                    @if($order->customer)
                        <a href="{{ route('customers.show', $order->customer->id) }}" class="text-decoration-none">
                            {{ $order->customer->name }}
                        </a>
                    @else
                        <span class="detail-empty-value">Khách hàng đã bị xóa</span>
                    @endif
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Số điện thoại" :value="$order->customer?->phone" />
                <x-admin.detail.info-item label="Nhân viên phụ trách" :value="$order->employee?->name" />
                <x-admin.detail.info-item label="Dịch vụ" :value="$order->service?->name" />
                <x-admin.detail.info-item label="Nguồn đơn">
                    @if($order->booking)
                        <a href="{{ route('bookings.show', $order->booking) }}" class="text-decoration-none">
                            <i class="bi bi-calendar-check me-1"></i>Lịch hẹn {{ $order->booking->code }}
                        </a>
                    @else
                        <span class="detail-empty-value">Nhập trực tiếp</span>
                    @endif
                </x-admin.detail.info-item>
                <x-admin.detail.info-item label="Ngày tạo" :value="$order->created_at?->format('d/m/Y H:i')" />
                <x-admin.detail.info-item label="Thanh toán" :value="$order->payment_status_label" />
            </x-admin.detail.info-grid>

            @if($order->notes)
                <div class="mt-4">
                    <div class="detail-field__label mb-2">Ghi chú</div>
                    <div class="detail-text">{{ $order->notes }}</div>
                </div>
            @endif
        </x-admin.detail.panel>

        <x-admin.detail.panel title="Chi tiết mặt hàng" icon="bi-list-check" :iconClass="'bg-info-subtle text-info'" flush>
            <x-slot:header>
                <span class="text-muted small">{{ $order->items->count() }} mục</span>
            </x-slot:header>

            @if($order->items->isEmpty())
                <x-admin.detail.empty message="Đơn hàng chưa có mặt hàng nào" icon="bi-bag" />
            @else
                <div class="table-responsive">
                    <table class="table table-hover detail-table">
                        <thead>
                            <tr>
                                <th>Dịch vụ</th>
                                <th>Loại đồ giặt</th>
                                <th class="text-end">Khối lượng (kg)</th>
                                <th class="text-end">Đơn giá</th>
                                <th class="text-end">Số lượng</th>
                                <th class="text-end">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $item)
                                <tr>
                                    <td class="fw-semibold">{{ $item->service?->name ?: $item->item_name }}</td>
                                    <td>{{ $item->garment?->name ?: $item->item_type }}</td>
                                    <td class="text-end">{{ $item->weight !== null ? format_weight($item->weight) : '—' }}</td>
                                    <td class="text-end"><x-admin.detail.money :value="$item->price" /></td>
                                    <td class="text-end">{{ number_format($item->quantity) }}</td>
                                    <td class="text-end fw-semibold"><x-admin.detail.money :value="$item->subtotal" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-admin.detail.panel>

        <x-admin.detail.panel title="Lịch sử trạng thái" icon="bi-clock-history" :iconClass="'bg-secondary-subtle text-secondary'">
            <div class="detail-timeline">
                @foreach($statusFlow as $key => $label)
                    <div class="detail-timeline__item {{ $key === $order->status ? 'detail-timeline__item--current' : 'detail-timeline__item--muted' }}">
                        <span class="detail-timeline__dot"></span>
                        <span>{{ $label }}</span>
                        @if($key === $order->status)
                            <span class="badge bg-primary-subtle text-primary-emphasis border border-primary px-2 py-1 rounded-pill ms-auto">Hiện tại</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-admin.detail.panel>
    </div>

    {{-- ============ CỘT PHỤ (4/12) ============ --}}
    <div class="col-lg-4">
        <x-admin.detail.panel title="Tổng thanh toán" icon="bi-calculator" :iconClass="'bg-success-subtle text-success'">
            <div class="d-flex justify-content-between align-items-center py-2">
                <span class="detail-summary__label">Tạm tính</span>
                <x-admin.detail.money :value="$order->subtotal" class="fw-semibold" />
            </div>
            <div class="d-flex justify-content-between align-items-center py-2">
                <span class="detail-summary__label">Tiền giảm voucher</span>
                <x-admin.detail.money :value="$order->discount_by_promotion" :negative="$hasPromotionDiscount" class="text-danger" />
            </div>
            <div class="d-flex justify-content-between align-items-start py-2">
                <span class="detail-summary__label">
                    Tiền giảm do điểm
                    @if($order->points_used > 0)
                        <small class="d-block">({{ number_format($order->points_used) }} điểm)</small>
                    @endif
                </span>
                <x-admin.detail.money :value="$order->discount_by_points" :negative="$hasPointsDiscount" class="text-danger" />
            </div>

            <div class="detail-summary d-flex justify-content-between align-items-center">
                <span class="detail-summary__total">Tổng thanh toán</span>
                <x-admin.detail.money :value="$order->total_amount" class="detail-summary__total text-primary" />
            </div>

            @if($order->promotion)
                <div class="d-flex justify-content-end">
                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary px-3 py-2 rounded-pill">
                        <i class="fas fa-ticket me-1"></i>{{ $order->promotion->name }} ({{ $order->promotion->code }})
                    </span>
                </div>
            @endif
        </x-admin.detail.panel>

        <x-admin.detail.panel title="Thông tin giao nhận" icon="bi-truck" :iconClass="'bg-warning-subtle text-warning-emphasis'">
            @if($order->delivery)
                <x-admin.detail.info-grid :columns="1">
                    <x-admin.detail.info-item label="Hình thức">
                        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary px-3 py-2 rounded-pill">
                            <i class="fas {{ in_array($order->delivery->method, ['home_pickup', 'pickup', 'nhan_do']) ? 'fa-home' : 'fa-truck' }} me-1"></i>
                            {{ $order->delivery->type_label }}
                        </span>
                    </x-admin.detail.info-item>
                    <x-admin.detail.info-item label="Trạng thái">
                        <x-admin.status-badge :status="$order->delivery->status" :enum="\App\Enums\DeliveryStatus::class" :pill="false" />
                    </x-admin.detail.info-item>
                    <x-admin.detail.info-item label="Ngày giao dự kiến" :value="$order->delivery->pickup_date?->format('d/m/Y')" />
                    <x-admin.detail.info-item label="Địa chỉ giao hàng" :value="$order->delivery->address" />
                </x-admin.detail.info-grid>

                @if($order->delivery->notes)
                    <div class="mt-3">
                        <div class="detail-field__label mb-2">Ghi chú giao nhận</div>
                        <div class="detail-text">{{ $order->delivery->notes }}</div>
                    </div>
                @endif

                <div class="mt-3">
                    <a href="{{ route('deliveries.show', $order->delivery->id) }}" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Chi tiết phiếu giao nhận
                    </a>
                </div>
            @else
                <x-admin.detail.empty message="Đơn hàng chưa có lịch giao nhận" icon="bi-truck" />
            @endif
        </x-admin.detail.panel>
    </div>
</div>
@endsection
