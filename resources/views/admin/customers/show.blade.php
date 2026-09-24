@extends('layouts.app')

@section('title', 'Chi tiết khách hàng - Giặt Ủi Pro')
@section('page-title', 'Chi tiết khách hàng')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Quay lại
    </a>
    <a href="{{ route('customers.edit', $customer->id) }}" class="btn btn-primary btn-sm">
        <i class="bi bi-pencil me-1"></i>Chỉnh sửa
    </a>
</div>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Thông tin khách hàng</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr><td><strong>Mã</strong></td><td>{{ $customer->code }}</td></tr>
                    <tr><td><strong>Họ tên</strong></td><td>{{ $customer->name }}</td></tr>
                    <tr><td><strong>Email</strong></td><td>{{ $customer->email ?: '-' }}</td></tr>
                    <tr><td><strong>Số điện thoại</strong></td><td>{{ $customer->phone ?: '-' }}</td></tr>
                    <tr><td><strong>Loại</strong></td>
                        <td>
                            @if($customer->type === 'VIP')
                                <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill"><i class="fas fa-crown me-1"></i>VIP</span>
                            @elseif($customer->type === 'Thường')
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-user me-1"></i>Thường</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary px-3 py-2 rounded-pill"><i class="fas fa-user-plus me-1"></i>Mới</span>
                            @endif
                        </td>
                    </tr>
                    <tr><td><strong>Điểm tích lũy</strong></td><td><strong>{{ number_format($customer->points) }}</strong> điểm</td></tr>
                    <tr><td><strong>Ngày đăng ký</strong></td><td>{{ $customer->created_at?->format('d/m/Y H:i') }}</td></tr>
                    <tr><td><strong>Trạng thái</strong></td>
                        <td>
                            @if($customer->deleted_at)
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-trash me-1"></i>Đã xóa</span>
                            @else
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Hoạt động</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr><td><strong>Tổng chi tiêu</strong></td><td><strong>{{ number_format($totalSpent) }} VNĐ</strong></td></tr>
                    <tr><td><strong>Tổng đơn hàng</strong></td><td>{{ $orderCount }}</td></tr>
                </table>
            </div>
        </div>
        @if($customer->address)
        <div class="mt-3">
            <strong>Địa chỉ:</strong> {{ $customer->address }}
        </div>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Lịch sử đơn hàng</h5>
    </div>
    <div class="card-body p-0">
        @if($orders->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Mã đơn</th><th>Dịch vụ</th><th>Tổng tiền</th><th>Trạng thái</th><th>Ngày tạo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $order)
                    <tr>
                        <td><strong>{{ $order->code }}</strong></td>
                        <td>{{ $order->service?->name ?: '-' }}</td>
                        <td>{{ number_format($order->total_amount) }} VNĐ</td>
                        @if($order->status === 'completed')
                            <span class="badge bg-success-subtle text-success border border-success px-2 py-1 rounded-pill"><i class="fas fa-check-circle"></i></span>
                        @elseif($order->status === 'cancelled')
                            <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1 rounded-pill"><i class="fas fa-x-circle"></i></span>
                        @elseif($order->status === 'pending')
                            <span class="badge bg-warning-subtle text-warning border border-warning px-2 py-1 rounded-pill"><i class="fas fa-hourglass"></i></span>
                        @elseif($order->status === 'processing')
                            <span class="badge bg-info-subtle text-info border border-info px-2 py-1 rounded-pill"><i class="fas fa-cog"></i></span>
                        @elseif($order->status === 'delivering')
                            <span class="badge bg-primary-subtle text-primary border border-primary px-2 py-1 rounded-pill"><i class="fas fa-truck"></i></span>
                        @elseif($order->status === 'washing')
                            <span class="badge bg-warning-subtle text-warning border border-warning px-2 py-1 rounded-pill"><i class="fas fa-washer"></i></span>
                        @elseif($order->status === 'washed')
                            <span class="badge bg-info-subtle text-info border border-info px-2 py-1 rounded-pill"><i class="fas fa-tshirt-pocket"></i></span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary px-2 py-1 rounded-pill">{{ $order->status }}</span>
                        @endif
                        <td>{{ $order->created_at?->format('d/m/Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $orders->links() }}
        @else
        <p class="text-center text-muted py-4">Chưa có đơn hàng</p>
        @endif
    </div>
</div>
@endsection
