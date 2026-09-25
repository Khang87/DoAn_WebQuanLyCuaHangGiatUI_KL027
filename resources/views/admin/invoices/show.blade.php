@extends('layouts.app')

@section('title', 'Chi tiết hóa đơn - Sky Laundry')
@section('page-title', 'Chi tiết hóa đơn')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Quay lại
    </a>
    <div class="d-flex gap-2">
        <a href="{{ route('invoices.export-excel', $invoice->id) }}" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i>Xuất Excel
        </a>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>In hóa đơn
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="invoice-header mb-4">
            <div class="row">
                <div class="col-md-6">
                    <div class="fw-bold fs-5">SKY LAUNDRY</div>
                    <small class="text-muted d-block">
                        Số 123 Đường Lê Lợi, Quận 1, TP. HCM<br>
                        Hotline: 0909.123.456
                    </small>
                </div>
                <div class="col-md-6 text-md-end">
                    <h5 class="fw-bold mb-1">HÓA ĐƠN DỊCH VỤ GIẶT ỦI</h5>
                    @php
                        $badgeClass = $invoice->status === 'paid' ? 'bg-success-subtle text-success border-success' :
                                      ($invoice->status === 'partial' ? 'bg-warning-subtle text-warning border-warning' :
                                      'bg-danger-subtle text-danger border-danger');
                    @endphp
                    @if($invoice->status === 'paid')
                        <span class="badge {{ $badgeClass }} px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Đã thanh toán</span>
                    @elseif($invoice->status === 'partial')
                        <span class="badge {{ $badgeClass }} px-3 py-2 rounded-pill"><i class="fas fa-hourglass me-1"></i>Một phần</span>
                    @else
                        <span class="badge {{ $badgeClass }} px-3 py-2 rounded-pill"><i class="fas fa-x-circle me-1"></i>Chưa thanh toán</span>
                    @endif
                    
                        <div class="d-flex gap-2 mt-3">
                            @if($invoice->status !== 'paid')
                                <form action="{{ route('invoices.update-status', $invoice) }}" method="POST" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="status" value="paid">
                                    <button type="submit" class="btn btn-sm btn-outline-success"><i class="fas fa-check me-1"></i>Đánh dấu đã thanh toán</button>
                                </form>
                            @endif
                            @if($invoice->status !== 'unpaid')
                                <form action="{{ route('invoices.update-status', $invoice) }}" method="POST" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="status" value="unpaid">
                                    <button type="submit" class="btn btn-sm btn-outline-warning"><i class="fas fa-undo me-1"></i>Chuyển về chờ thanh toán</button>
                                </form>
                            @endif
                        </div>
<table class="table table-borderless table-sm mt-2 mb-0 text-start text-md-end">
                        <tr>
                            <td class="text-muted">Mã hóa đơn:</td>
                            <td><strong>{{ $invoice->code ?: '-' }}</strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Ngày lập:</td>
                            <td>{{ $invoice->created_at?->format('d/m/Y H:i') ?: now()->format('d/m/Y H:i') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Mã đơn:</td>
                            <td>{{ $invoice->order?->code ?: '-' }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        @php
            $customer = $invoice->order?->customer;
            $payment = $invoice->payments?->last();
        @endphp

        <div class="invoice-customer mb-4">
            <h6 class="fw-bold mb-2">THÔNG TIN KHÁCH HÀNG</h6>
            <div class="row g-2">
                <div class="col-md-6">
                    <table class="table table-borderless table-sm mb-0">
                        <tr>
                            <td class="text-muted">Họ tên:</td>
                            <td>{{ $customer?->name ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">SĐT:</td>
                            <td>{{ $customer?->phone ?: '-' }}</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless table-sm mb-0">
                        <tr>
                            <td class="text-muted">Địa chỉ:</td>
                            <td>{{ $customer?->address ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Phương thức:</td>
                            <td>
                                @if($payment)
                                    @if($payment->method === 'cash')
                                        <i class="bi bi-cash-coin me-1"></i>Tiền mặt
                                    @elseif($payment->method === 'bank_transfer')
                                        <i class="bi bi-bank me-1"></i>Chuyển khoản
                                    @else
                                        <i class="bi bi-wallet2 me-1"></i>Ví điện tử
                                    @endif
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            @if($invoice->order?->notes || $payment?->notes)
            <div class="mt-2">
                <strong>Ghi chú:</strong> {{ $invoice->order?->notes ?: ($payment?->notes ?: '-') }}
            </div>
            @endif
        </div>

        <div class="invoice-items mb-4">
            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Tên Dịch Vụ / Loại Đồ</th>
                            <th>Đơn Vị Tính</th>
                            <th class="text-end">Số Lượng</th>
                            <th class="text-end">Đơn Giá (VNĐ)</th>
                            <th class="text-end">Thành Tiền (VNĐ)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($invoice->order?->items as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $item->service?->name ?: ($item->item_name ?: '-') }}</td>
                            <td>{{ $item->service?->unit ?: 'kg' }}</td>
                            <td class="text-end">{{ $item->quantity ?? 0 }}</td>
                            <td class="text-end">{{ number_format($item->price ?? 0) }}</td>
                            <td class="text-end">{{ number_format($item->subtotal ?? 0) }} VNĐ</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">Chưa có dữ liệu dịch vụ</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="invoice-summary">
            <div class="row justify-content-end">
                <div class="col-md-5">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td class="text-muted">Tạm tính:</td>
                            <td class="text-end">{{ number_format($invoice->order?->items?->sum('subtotal') ?? $invoice->total) }} VNĐ</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Giảm giá:</td>
                            <td class="text-end">{{ number_format($invoice->order?->discount_amount ?? 0) }} VNĐ</td>
                        </tr>
                        <tr class="border-top">
                            <td class="fw-bold fs-5">TỔNG CỘNG:</td>
                            <td class="text-end fw-bold fs-5 text-primary">{{ number_format($invoice->total) }} VNĐ</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    @media print {
        .sidebar-wrapper, .navbar-custom, .invoice-header .col-md-6:first-child, .btn, .d-flex.gap-2 {
            display: none !important;
        }
        .invoice-header .col-md-6:last-child {
            text-align: right !important;
        }
        .content-wrapper {
            padding: 0 !important;
        }
        .card {
            border: none !important;
            box-shadow: none !important;
        }
    }
</style>
@endpush
