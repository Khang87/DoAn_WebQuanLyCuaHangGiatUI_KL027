@extends('layouts.app')

@section('title', 'Chi tiết thanh toán - Giặt Ủi Pro')
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
        <table class="table table-borderless">
            <tr><td><strong>Đơn hàng</strong></td><td>#{{ $payment->order?->code }}</td></tr>
            <tr><td><strong>Số tiền</strong></td><td><strong>{{ number_format($payment->amount) }} VNĐ</strong></td></tr>
            <tr><td><strong>Phương thức</strong></td>
                <td>
                    @if($payment->method === 'cash') Tiền mặt
                    @elseif($payment->method === 'bank_transfer') Chuyển khoản
                    @elseif($payment->method === 'e_wallet') Ví điện tử
                    @else {{ $payment->method }} @endif
                </td>
            </tr>
            <tr><td><strong>Trạng thái</strong></td>
                <td>
                    @if($payment->status === 'paid')
                        <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Đã thanh toán</span>
                    @elseif($payment->status === 'partial')
                        <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill"><i class="fas fa-hourglass me-1"></i>Một phần</span>
                    @else
                        <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-x-circle me-1"></i>Chưa thanh toán</span>
                    @endif
                </td>
            </tr>
            <tr><td><strong>Ngày tạo</strong></td><td>{{ $payment->created_at?->format('d/m/Y H:i') }}</td></tr>
        </table>
    </div>
</div>
@endsection
