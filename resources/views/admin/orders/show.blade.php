@extends('layouts.app')

@section('title', 'Chi tiết đơn hàng - Sky Laundry')
@section('page-title', 'Chi tiết đơn hàng')

@section('content')
@php
    $orderStatusLabels = [
        'pending' => 'Chờ tiếp nhận',
        'received' => 'Đã nhận đồ',
        'sorting' => 'Đang phân loại',
        'processing' => 'Đang giặt / Xử lý',
        'washed' => 'Đã giặt xong',
        'delivering' => 'Đang giao đồ',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Đã hủy',
    ];
@endphp
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Quay lại
    </a>
    <div class="d-flex gap-2">
        @if($order->invoice)
            <a href="{{ route('invoices.show', $order->invoice->id) }}" class="btn btn-outline-info">
                <i class="fas fa-eye me-1"></i> Xem hóa đơn
            </a>
        @elseif(in_array($order->status, ['completed', 'washed', 'delivering']))
            <a href="{{ route('invoices.create', ['order_id' => $order->id]) }}" class="btn btn-primary">
                <i class="fas fa-file-invoice me-1"></i> Tạo hóa đơn
            </a>
        @endif
        @if($order->can_edit)
            <a href="{{ route('orders.edit', $order->id) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-pencil me-1"></i>Chỉnh sửa
            </a>
        @endif
        @if($order->is_locked)
            <span class="badge bg-secondary text-white px-3 py-2 rounded-pill d-flex align-items-center" title="Đã quyết toán">
                <i class="bi bi-lock me-1"></i>Đã quyết toán
            </span>
        @endif
    </div>
</div>

@if($order->booking)
<div class="alert alert-info mb-4 d-flex align-items-center gap-2">
    <i class="bi bi-journal-bookmark text-primary"></i>
    <div>
        <strong>Đơn hàng từ lịch hẹn:</strong> <a href="{{ route('bookings.show', $order->booking) }}">{{ $order->booking->code }}</a>
    </div>
</div>
@endif

<div class="card mb-4">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Đơn hàng {{ $order->code }}</h5>
        @if($order->is_locked)
            <span class="badge bg-light text-dark px-3 py-2 rounded-pill">
                <i class="bi bi-lock me-1"></i>Đã quyết toán
            </span>
        @endif
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr><td><strong>Khách hàng</strong></td><td>{{ $order->customer?->name }}</td></tr>
                    <tr><td><strong>Dịch vụ</strong></td><td>{{ $order->service?->name }}</td></tr>
                    <tr><td><strong>Trạng thái</strong></td>
                        <td>
                             @php $status = $orderStatusLabels[$order->status] ?? $orderStatusLabels['pending']; @endphp
                             @if($order->status === 'completed')
                                 <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>{{ $status }}</span>
                             @elseif($order->status === 'cancelled')
                                 <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-x-circle me-1"></i>{{ $status }}</span>
                             @elseif($order->status === 'delivering')
                                 <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2 rounded-pill"><i class="fas fa-truck me-1"></i>{{ $status }}</span>
                             @elseif($order->status === 'processing')
                                 <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill"><i class="fas fa-washer me-1"></i>{{ $status }}</span>
                             @elseif($order->status === 'washed')
                                 <span class="badge bg-info-subtle text-info border border-info px-3 py-2 rounded-pill"><i class="fas fa-tshirt-pocket me-1"></i>{{ $status }}</span>
                             @else
                                 <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill"><i class="fas fa-clock me-1"></i>{{ $status }}</span>
                             @endif
                        </td>
                    </tr>
                    <tr><td><strong>Ngày tạo</strong></td><td>{{ $order->created_at?->format('d/m/Y H:i') }}</td></tr>
                    <tr><td><strong>Ghi chú</strong></td><td>{{ $order->notes ?: '-' }}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless mb-0">
                    <tbody>
                        <tr>
                            <td>Tạm tính:</td>
                            <td class="text-end">{{ number_format($order->subtotal) }} VNĐ</td>
                        </tr>
                        <tr>
                            <td>Tiền giảm voucher:</td>
                            <td class="text-end text-danger">-{{ number_format($order->discount_by_promotion) }} VNĐ</td>
                        </tr>
                        <tr>
                            <td>Tiền giảm do điểm:</td>
                            <td class="text-end text-danger">-{{ number_format($order->discount_by_points) }} VNĐ
                                @if($order->points_used > 0)
                                    <small class="text-muted">({{ number_format($order->points_used) }} điểm)</small>
                                @endif
                            </td>
                        </tr>
                        <tr class="table-light">
                            <td class="fw-bold fs-5">TỔNG THANH TOÁN:</td>
                            <td class="text-end fw-bold fs-5 text-primary">{{ number_format($order->total_amount) }} VNĐ</td>
                        </tr>
                    </tbody>
                </table>
                @if($order->promotion)
                    <div class="text-end mt-1">
                        <span class="badge bg-primary-subtle text-primary border border-primary">
                            <i class="fas fa-ticket me-1"></i>{{ $order->promotion->name }} ({{ $order->promotion->code }})
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Thông tin giao nhận -->
<div class="card mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="fas fa-truck me-2"></i>Thông tin giao nhận</h5>
    </div>
    <div class="card-body">
        @if($order->delivery)
            <table class="table table-borderless mb-0">
                <tr><td><strong>Hình thức giao nhận</strong></td>
                    <td>
                        <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2 rounded-pill">
                            <i class="fas {{ $order->delivery->method === 'home_pickup' || $order->delivery->method === 'pickup' || $order->delivery->method === 'nhan_do' ? 'fa-home' : 'fa-truck' }} me-1"></i>
                            {{ $order->delivery->type_label }}
                        </span>
                    </td>
                </tr>
                <tr><td><strong>Trạng thái giao nhận</strong></td>
                    <td><span class="badge {{ $order->delivery->status_badge_class }} px-3 py-2 rounded-pill">{{ $order->delivery->status_label }}</span></td>
                </tr>
                @if($order->delivery->address)
                    <tr><td><strong>Địa chỉ giao hàng</strong></td><td>{{ $order->delivery->address }}</td></tr>
                @endif
                @if($order->delivery->pickup_date)
                    <tr><td><strong>Ngày giao dự kiến</strong></td><td>{{ $order->delivery->pickup_date->format('d/m/Y') }}</td></tr>
                @endif
                @if($order->delivery->notes)
                    <tr><td><strong>Ghi chú</strong></td><td>{{ $order->delivery->notes }}</td></tr>
                @endif
            </table>
        @else
            <p class="text-center text-muted py-3">
                <i class="fas fa-info-circle me-1"></i>
                Đơn hàng này chưa có lịch giao nhận.
                <a href="#" class="text-primary">Tạo lịch giao nhận</a>
            </p>
        @endif
    </div>
</div>

@if($order->items->count() > 0)
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Chi tiết mặt hàng</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Dịch vụ</th><th>Loại đồ giặt</th><th>Khối lượng (kg)</th><th>Đơn giá</th><th>Số lượng</th><th>Thành tiền</th></tr></thead>
                <tbody>
                    @foreach($order->items as $item)
                    <tr>
                        <td>{{ $item->service?->name ?: $item->item_name }}</td>
                        <td>{{ $item->garment?->name ?: $item->item_type }}</td>
                        <td>{{ $item->weight !== null ? number_format((float)$item->weight, 2) : '—' }}</td>
                        <td>{{ number_format($item->price) }} VNĐ</td>
                        <td>{{ number_format($item->quantity) }}</td>
                        <td>{{ number_format($item->subtotal) }} VNĐ</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<div class="card">
    <div class="card-header"><h5 class="mb-0">Lịch sử trạng thái</h5></div>
    <div class="card-body">
        <div class="timeline">
            @foreach($statusFlow as $key => $label)
            <div class="d-flex align-items-center mb-3 @if($key === $order->status) fw-bold @else text-muted @endif">
                <div class="status-dot {{ $key === $order->status ? 'bg-primary' : 'bg-secondary' }} me-3"></div>
                <span>{{ $label }}</span>
                @if($key === $order->status)
                <span class="badge bg-primary ms-3">(Hiện tại)</span>
                @endif
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection