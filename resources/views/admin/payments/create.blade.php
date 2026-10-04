@extends('layouts.app')

@section('title', 'Ghi nhận thanh Toán - Sky Laundry')
@section('page-title', 'Ghi nhận thanh toán')

@section('content')
@push('styles')
<style>
    #paymentForm .form-control:read-only,
    #paymentForm .form-control:disabled,
    #paymentForm .form-select:disabled {
        background-color: #e9ecef !important;
        color: #6c757d !important;
        cursor: not-allowed;
        opacity: 1;
    }

    #paymentForm .payment-form__editable-muted {
        background-color: #e9ecef;
    }
</style>
@endpush

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Thông tin thanh toán</h5>
            <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        @if($preselectedOrder)
            <div class="alert alert-primary" role="status">
                <div class="fw-semibold">Thanh toán đơn {{ $preselectedOrder->MaDonHang }}</div>
                <div>Khách hàng: {{ $preselectedOrder->khachHang?->HoTen ?: '—' }}</div>
                <div>
                    Dịch vụ:
                    {{ $preselectedOrder->chiTietDonHangs->map(fn ($item) => $item->dichVu?->TenDichVu)->filter()->unique()->join(', ') ?: '—' }}
                </div>
                <div>Số tiền còn phải thu: {{ number_format((float) $preselectedAmount) }} đ</div>
            </div>
        @endif

        <form action="{{ route('payments.store') }}" method="POST" id="paymentForm">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Đơn hàng</label>
                    <select class="form-select" name="order_id" @disabled($preselectedOrder)>
                        <option value="">Chọn đơn hàng</option>
                        @foreach($orders as $order)
                        <option value="{{ $order->DonHangID }}" data-order-code="{{ $order->MaDonHang }}" @selected((string) old('order_id', $preselectedOrder?->DonHangID) === (string) $order->DonHangID)>
                            {{ $order->MaDonHang }} - {{ $order->khachHang?->HoTen }}
                        </option>
                        @endforeach
                    </select>
                    @if($preselectedOrder)
                        <input type="hidden" name="order_id" value="{{ $preselectedOrder->getKey() }}">
                    @endif
                </div>
                @if(! $preselectedOrder || $preselectedInvoice)
                    <div class="col-md-6">
                        <label class="form-label">Hóa đơn (tự động lấy từ đơn hàng)</label>
                        <select class="form-select" name="invoice_id" @disabled($preselectedOrder)>
                            @unless($preselectedOrder)
                                <option value="">Chọn hóa đơn (tùy chọn)</option>
                            @endunless
                            @foreach($invoices as $inv)
                                <option value="{{ $inv->HoaDonID }}" data-order-code="{{ $inv->donHang?->MaDonHang }}" @selected((string) old('invoice_id', $preselectedInvoice?->HoaDonID) === (string) $inv->HoaDonID)>
                                    {{ $inv->MaHoaDon }} - {{ $inv->donHang?->khachHang?->HoTen }}
                                </option>
                            @endforeach
                        </select>
                        @if($preselectedOrder && $preselectedInvoice)
                            <input type="hidden" name="invoice_id" value="{{ $preselectedInvoice->getKey() }}">
                        @endif
                    </div>
                @endif
                <div class="col-md-6">
                    @if($preselectedOrder)
                        <label class="form-label">Số tiền thanh toán</label>
                        <input type="number" class="form-control" value="{{ $preselectedAmount }}" min="0" step="1000" disabled aria-describedby="payment-amount-help">
                        <input type="hidden" name="amount" value="{{ $preselectedAmount }}">
                    @else
                        <label class="form-label">Số tiền thanh toán <span class="text-danger ms-1">*</span></label>
                        <input type="number" class="form-control" name="amount" value="{{ old('amount') }}" placeholder="250000" min="0" step="1000" required>
                    @endif
                    @if($preselectedOrder)
                        <div class="form-text" id="payment-amount-help">Số tiền còn phải thu của đơn hàng.</div>
                    @endif
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phương thức <span class="text-danger ms-1">*</span></label>
                    <select class="form-select" name="method" required>
                        <option value="cash" @selected(old('method', 'cash') === 'cash')>Tiền mặt</option>
                        <option value="bank_transfer" @selected(old('method') === 'bank_transfer')>Chuyển khoản / QR</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trạng thái</label>
                    <select class="form-select" disabled aria-describedby="payment-status-help">
                        <option value="{{ \App\Enums\PaymentStatus::Paid->value }}" selected>{{ \App\Enums\PaymentStatus::Paid->label() }}</option>
                    </select>
                    <input type="hidden" name="status" value="{{ \App\Enums\PaymentStatus::Paid->value }}">
                    <div class="form-text" id="payment-status-help">Giao dịch được ghi nhận là thành công khi lưu.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ngày thanh toán</label>
                    <input type="datetime-local" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}" disabled aria-describedby="payment-date-help">
                    <div class="form-text" id="payment-date-help">Thời gian được tự động ghi nhận khi lưu thanh toán.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mã giao dịch</label>
                    <input type="text" class="form-control" id="transaction_code" name="transaction_code" value="{{ old('transaction_code') }}" placeholder="Mã giao dịch tự động" maxlength="100" readonly aria-describedby="transaction-code-help">
                    <div class="form-text" id="transaction-code-help">Mã được hệ thống tự tạo theo phương thức thanh toán.</div>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary">Hủy</a>
                <button type="submit" class="btn btn-primary">Lưu thanh toán</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('paymentForm');
        const methodSelect = form?.querySelector('[name="method"]');
        const orderSelect = form?.querySelector('[name="order_id"]:not([type="hidden"])');
        const invoiceSelect = form?.querySelector('[name="invoice_id"]');
        const transactionCode = form?.querySelector('#transaction_code');

        if (!methodSelect || !transactionCode) {
            return;
        }

        let lastSuggestedCode = '';

        function selectedOrderCode() {
            const selectedOrder = orderSelect?.selectedOptions[0];
            if (selectedOrder?.dataset.orderCode) {
                return selectedOrder.dataset.orderCode;
            }

            const selectedInvoice = invoiceSelect?.selectedOptions[0];
            return selectedInvoice?.dataset.orderCode || 'TT';
        }

        function suggestTransactionCode() {
            const isCash = methodSelect.value === 'cash';
            transactionCode.placeholder = isCash
                ? 'Mã giao dịch tiền mặt tự động'
                : 'Nhập mã giao dịch / Mã tham chiếu từ Ngân hàng / Ví';

            const prefix = isCash ? 'TM' : 'CK';
            const now = new Date();
            const timestamp = [
                now.getFullYear(),
                String(now.getMonth() + 1).padStart(2, '0'),
                String(now.getDate()).padStart(2, '0'),
                String(now.getHours()).padStart(2, '0'),
                String(now.getMinutes()).padStart(2, '0'),
                String(now.getSeconds()).padStart(2, '0'),
            ].join('');
            lastSuggestedCode = prefix + '_' + selectedOrderCode() + '_' + timestamp;
            transactionCode.value = lastSuggestedCode;
        }

        methodSelect.addEventListener('change', suggestTransactionCode);

        orderSelect?.addEventListener('change', function() {
            suggestTransactionCode();
        });

        invoiceSelect?.addEventListener('change', function() {
            suggestTransactionCode();
        });

        suggestTransactionCode();
    });
</script>
@endpush
