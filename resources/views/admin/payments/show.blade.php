@extends('layouts.app')

@section('title', 'Chi tiết thanh toán - Sky Laundry')
@section('page-title', 'Chi tiết thanh toán')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Quay lại
    </a>
</div>

<div class="card">
    <div class="card-body">
        <h5 class="mb-3">Thông tin thanh toán</h5>
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr><td><strong>Mã thanh toán</strong></td><td>TT{{ $payment->id }}</td></tr>
                    <tr><td><strong>Đơn hàng</strong></td><td><a href="{{ route('orders.show', $payment->order_id) }}">{{ $payment->order?->code }}</a></td></tr>
                    <tr><td><strong>Hóa đơn</strong></td><td>{{ $payment->invoice?->code ?: 'Chưa liên kết' }}</td></tr>
                    <tr><td><strong>Số tiền</strong></td><td><strong>{{ number_format($payment->amount) }} VNĐ</strong></td></tr>
                    <tr><td><strong>Phương thức</strong></td>
                        <td>
                            <i class="bi {{ $payment->getMethodIcon() }} me-1"></i>{{ $payment->getMethodLabel() }}
                        </td>
                    </tr>
                    <tr><td><strong>Trạng thái</strong></td>
                        <td>
                            <span class="badge {{ $payment->getStatusBadgeClass() }} px-3 py-2 rounded-pill">
                                <i class="fas fa-{{ $payment->status === 'paid' ? 'check-circle' : ($payment->status === 'partial' ? 'clock' : ($payment->status === 'failed' ? 'times-circle' : ($payment->status === 'refunded' ? 'undo' : 'hourglass'))) }} me-1"></i>{{ $payment->getStatusLabel() }}
                            </span>
                        </td>
                    </tr>
                    <tr><td><strong>Mã giao dịch</strong></td><td>{{ $payment->transaction_code ?: '-' }}</td></tr>
                    <tr><td><strong>Ngày thanh toán</strong></td><td>{{ $payment->paid_at?->format('d/m/Y H:i') ?? $payment->created_at?->format('d/m/Y H:i') }}</td></tr>
                    <tr><td><strong>Ngày tạo</strong></td><td>{{ $payment->created_at?->format('d/m/Y H:i') }}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                @if($payment->order?->customer)
                <table class="table table-borderless">
                    <tr><td colspan="2"><strong>Thông tin khách hàng</strong></td></tr>
                    <tr><td>Họ tên</td><td>{{ $payment->order->customer->name }}</td></tr>
                    <tr><td>SĐT</td><td>{{ $payment->order->customer->phone }}</td></tr>
                    <tr><td>Địa chỉ</td><td>{{ $payment->order->customer->address }}</td></tr>
                </table>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection