@extends('layouts.app')

@section('title', 'Chi tiết hóa đơn - Giặt Ủi Pro')
@section('page-title', 'Chi tiết hóa đơn')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Quay lại
    </a>
</div>

<div class="card">
    <div class="card-body">
        <h5 class="mb-3">Hóa đơn #{{ $invoice->code }}</h5>
        <table class="table table-borderless">
            <tr><td><strong>Đơn hàng</strong></td><td>#{{ $invoice->order?->code }}</td></tr>
            <tr><td><strong>Tổng tiền</strong></td><td><strong>{{ number_format($invoice->total) }} VNĐ</strong></td></tr>
            <tr><td><strong>Trạng thái</strong></td>
                <td>
                    @if($invoice->status === 'paid')
                        <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Đã thanh toán</span>
                    @elseif($invoice->status === 'partial')
                        <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill"><i class="fas fa-hourglass me-1"></i>Một phần</span>
                    @else
                        <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-x-circle me-1"></i>Chưa thanh toán</span>
                    @endif
                </td>
            </tr>
            <tr><td><strong>Ghi chú</strong></td><td>{{ $invoice->notes ?: '-' }}</td></tr>
            <tr><td><strong>Ngày tạo</strong></td><td>{{ $invoice->created_at?->format('d/m/Y H:i') }}</td></tr>
        </table>
    </div>
</div>
@endsection
