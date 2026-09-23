@extends('layouts.app')
@section('title', 'Chi Tiết Thanh Toán - Giặt Ủi Pro')
@section('page-title', 'Chi tiết thanh toán')
@section('content')
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <h4>{{ $payment->id ? 'TT' . str_pad($payment->id, 3, '0', STR_PAD_LEFT) : 'TT001' }}</h4>
        <span class="badge-status badge-{{ $payment->status === 'completed' ? 'completed' : 'pending' }}">{{ $payment->status === 'completed' ? 'Đã thanh toán' : 'Chưa thanh toán' }}</span>
    </div>
    <div class="row g-4">
        <div class="col-md-6"><div class="small text-muted">Mã đơn hàng</div><div class="fw-semibold">{{ $payment->order?->code ?? 'Chưa gắn đơn' }}</div></div>
        <div class="col-md-6"><div class="small text-muted">Số tiền</div><div class="fw-semibold text-primary">{{ number_format($payment->amount) }} VNĐ</div></div>
        <div class="col-md-6"><div class="small text-muted">Phương thức</div><div class="fw-semibold">{{ $payment->method === 'cash' ? 'Tiền mặt' : ($payment->method === 'transfer' ? 'Chuyển khoản' : 'Ví điện tử') }}</div></div>
        <div class="col-md-6"><div class="small text-muted">Ngày thanh toán</div><div class="fw-semibold">{{ $payment->created_at?->format('d/m/Y H:i') }}</div></div>
    </div>
    <div class="mt-4">
        <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary">Quay lại</a>
        <a href="{{ route('payments.edit', $payment) }}" class="btn btn-primary">Chỉnh sửa</a>
    </div>
</div></div>
@endsection
