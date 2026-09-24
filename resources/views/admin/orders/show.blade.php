@extends('layouts.app')

@section('title', 'Chi tiết đơn hàng - Giặt Ủi Pro')
@section('page-title', 'Chi tiết đơn hàng')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Quay lại
    </a>
    <a href="{{ route('orders.edit', $order->id) }}" class="btn btn-primary btn-sm">
        <i class="bi bi-pencil me-1"></i>Chỉnh sửa
    </a>
</div>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Đơn hàng #{{ $order->code }}</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr><td><strong>Khách hàng</strong></td><td>{{ $order->customer?->name }}</td></tr>
                    <tr><td><strong>Dịch vụ</strong></td><td>{{ $order->service?->name }}</td></tr>
                    <tr><td><strong>Trạng thái</strong></td>
                        <td>
                            @if($order->status === 'completed')
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Hoàn thành</span>
                            @elseif($order->status === 'cancelled')
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-x-circle me-1"></i>Đã hủy</span>
                            @elseif($order->status === 'pending')
                                <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill"><i class="fas fa-hourglass me-1"></i>Chờ xử lý</span>
                            @elseif($order->status === 'processing')
                                <span class="badge bg-info-subtle text-info border border-info px-3 py-2 rounded-pill"><i class="fas fa-cog me-1"></i>Đang xử lý</span>
                            @elseif($order->status === 'delivering')
                                <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2 rounded-pill"><i class="fas fa-truck me-1"></i>Đang giao</span>
                            @elseif($order->status === 'washing')
                                <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill"><i class="fas fa-washer me-1"></i>Đang giặt</span>
                            @elseif($order->status === 'washed')
                                <span class="badge bg-info-subtle text-info border border-info px-3 py-2 rounded-pill"><i class="fas fa-tshirt-pocket me-1"></i>Đã giặt xong</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary px-3 py-2 rounded-pill"><i class="fas fa-circle-notch me-1"></i>{{ $order->status }}</span>
                            @endif
                        </td>
                    </tr>
                    <tr><td><strong>Ngày tạo</strong></td><td>{{ $order->created_at?->format('d/m/Y H:i') }}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr><td><strong>Tổng tiền</strong></td><td><strong class="text-primary">{{ number_format($order->total_amount) }} VNĐ</strong></td></tr>
                    <tr><td><strong>Ghi chú</strong></td><td>{{ $order->notes ?: '-' }}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

@if($order->items->count() > 0)
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Chi tiết mặt hàng</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Mặt hàng</th><th>Loại</th><th>Đơn giá</th><th>SL</th><th>Thành tiền</th></tr></thead>
                <tbody>
                    @foreach($order->items as $item)
                    <tr>
                        <td>{{ $item->item_name }}</td>
                        <td>{{ $item->item_type }}</td>
                        <td>{{ number_format($item->price) }} VNĐ</td>
                        <td>{{ $item->quantity }}</td>
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
