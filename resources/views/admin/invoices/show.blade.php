@extends('layouts.app')
@section('title', 'Chi Tiết Hóa Đơn - Giặt Ủi Pro')
@section('page-title', 'Chi tiết hóa đơn')
@section('content')
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <span class="text-muted">Số hóa đơn</span>
            <h4 class="mb-0">{{ $invoice->code ?? ('HD' . str_pad($invoice->id ?? 1, 3, '0', STR_PAD_LEFT)) }}</h4>
        </div>
        <span class="badge-status badge-{{ $invoice->status === 'paid' ? 'completed' : 'pending' }}">{{ $invoice->status === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán' }}</span>
    </div>
    <div class="row g-4">
        <div class="col-md-6"><div class="small text-muted">Mã đơn hàng</div><div class="fw-semibold">{{ $invoice->order?->code ?? 'Chưa gắn đơn' }}</div></div>
        <div class="col-md-6"><div class="small text-muted">Khách hàng</div><div class="fw-semibold">{{ $invoice->order?->customer?->name ?? 'Chưa có' }}</div></div>
        <div class="col-md-6"><div class="small text-muted">Dịch vụ</div><div class="fw-semibold">{{ $invoice->order?->service?->name ?? '-' }}</div></div>
        <div class="col-md-6"><div class="small text-muted">Tổng tiền</div><div class="fw-semibold text-primary">{{ number_format($invoice->total ?? 0) }} VNĐ</div></div>
    </div>
    @if($invoice->notes)
    <div class="mt-3"><div class="small text-muted">Ghi chú</div><div class="text-muted">{{ $invoice->notes }}</div></div>
    @endif
    <div class="mt-4">
        <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">Quay lại</a>
        <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-primary">Chỉnh sửa</a>
    </div>
</div></div>
@endsection
