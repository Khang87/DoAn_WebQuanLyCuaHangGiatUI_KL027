@extends('layouts.app')

@section('title', 'Ghi nhận thanh Toán - Sky Laundry')
@section('page-title', 'Ghi nhận thanh toán')

@section('content')
@php($initialMethod = old('method', $preselectedOrder?->booking?->PhuongThucThanhToan === 'Chuyển khoản' ? 'bank_transfer' : 'cash'))
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
                <div>Số tiền phải thanh toán toàn bộ: {{ number_format((float) $preselectedAmount) }} đ</div>
            </div>
        @endif

        <form action="{{ route('payments.store') }}" method="POST" id="paymentForm"
            data-bank-start-url="{{ route('payments.bank-transfer.start') }}"
            data-bank-status-url="{{ route('payments.bank-transfer.status') }}">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Đơn hàng</label>
                    <select class="form-select" name="order_id" @disabled($preselectedOrder)>
                        <option value="">Chọn đơn hàng</option>
                        @foreach($orders as $order)
                        <option
                            value="{{ $order->DonHangID }}"
                            data-order-code="{{ $order->MaDonHang }}"
                            data-payment-reference="{{ \App\Support\BankTransferReference::forOrder($order->DonHangID) }}"
                            data-invoice-code="{{ $order->hoaDons->first()?->MaHoaDon ?: $order->MaDonHang }}"
                            data-invoice-ready="{{ $order->hoaDons->first() ? '1' : '0' }}"
                            data-amount="{{ $order->hoaDons->first()?->ThanhTien ?? $order->ThanhTien }}"
                            @selected((string) old('order_id', $preselectedOrder?->DonHangID) === (string) $order->DonHangID)
                        >
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
                                <option
                                    value="{{ $inv->HoaDonID }}"
                                    data-order-id="{{ $inv->DonHangID }}"
                                    data-order-code="{{ $inv->donHang?->MaDonHang }}"
                                    data-payment-reference="{{ $inv->DonHangID ? \App\Support\BankTransferReference::forOrder($inv->DonHangID) : '' }}"
                                    data-invoice-code="{{ $inv->MaHoaDon }}"
                                    data-invoice-ready="1"
                                    data-amount="{{ $inv->ThanhTien }}"
                                    @selected((string) old('invoice_id', $preselectedInvoice?->HoaDonID) === (string) $inv->HoaDonID)
                                >
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
                        <label class="form-label">Số tiền thanh toán toàn bộ</label>
                        <input type="number" class="form-control" value="{{ $preselectedAmount }}" min="0" step="1000" disabled aria-describedby="payment-amount-help">
                        <input type="hidden" name="amount" value="{{ $preselectedAmount }}">
                    @else
                        <label class="form-label">Số tiền thanh toán toàn bộ <span class="text-danger ms-1">*</span></label>
                        <input type="number" class="form-control" name="amount" value="{{ old('amount') }}" placeholder="250000" min="0" step="1000" required>
                        <div class="form-text">Nhập đúng toàn bộ số tiền phải trả của hóa đơn; không hỗ trợ thanh toán một phần.</div>
                    @endif
                    @if($preselectedOrder)
                        <div class="form-text" id="payment-amount-help">Phải thanh toán đủ toàn bộ số tiền hiển thị.</div>
                    @endif
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phương thức <span class="text-danger ms-1">*</span></label>
                    <select class="form-select" name="method" required>
                        <option value="cash" @selected($initialMethod === 'cash')>Tiền mặt</option>
                        <option value="bank_transfer" @selected($initialMethod === 'bank_transfer')>Chuyển khoản / QR</option>
                    </select>
                </div>
                <div class="col-12 d-none" id="paymentVietQrPanel">
                    <div class="border rounded p-3 p-lg-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-qr-code-scan me-2"></i>Chuyển khoản MB Bank</h6>
                        <x-admin.vietqr-payment
                            :amount="$preselectedAmount ?? old('amount', 0)"
                            :memo="$preselectedOrder ? \App\Support\BankTransferReference::forOrder($preselectedOrder->DonHangID) : ''"
                            id-prefix="payment-vietqr"
                        />
                    </div>
                </div>
                <div class="col-12 {{ $initialMethod === 'bank_transfer' ? '' : 'd-none' }}" id="paymentBankNotice" role="status" aria-live="polite">
                    <div class="alert alert-info mb-0 d-flex align-items-center gap-2">
                        <span class="spinner-border spinner-border-sm" data-bank-spinner aria-hidden="true"></span>
                        <span data-bank-status-text>Đang chuẩn bị thông tin chuyển khoản...</span>
                    </div>
                </div>
                <div class="col-md-6 {{ $initialMethod === 'bank_transfer' ? 'd-none' : '' }}" data-cash-only>
                    <label class="form-label">Trạng thái</label>
                    <select class="form-select" disabled aria-describedby="payment-status-help">
                        <option value="{{ \App\Enums\PaymentStatus::Paid->value }}" selected>{{ \App\Enums\PaymentStatus::Paid->label() }}</option>
                    </select>
                    <input type="hidden" name="status" value="{{ \App\Enums\PaymentStatus::Paid->value }}">
                    <div class="form-text" id="payment-status-help">Giao dịch chỉ thành công sau khi nhân viên xác nhận đã nhận đủ tiền mặt.</div>
                </div>
                <div class="col-md-6 {{ $initialMethod === 'bank_transfer' ? 'd-none' : '' }}" data-cash-only>
                    <label class="form-label">Ngày thanh toán</label>
                    <input type="datetime-local" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}" disabled aria-describedby="payment-date-help">
                    <div class="form-text" id="payment-date-help">Thời gian được tự động ghi nhận khi lưu thanh toán.</div>
                </div>
                <div class="col-md-6 {{ $initialMethod === 'bank_transfer' ? 'd-none' : '' }}" data-cash-only>
                    <label class="form-label">Mã giao dịch</label>
                    <input type="text" class="form-control" id="transaction_code" name="transaction_code" value="{{ old('transaction_code') }}" placeholder="Mã giao dịch tự động" maxlength="100" readonly aria-describedby="transaction-code-help">
                    <div class="form-text" id="transaction-code-help">Mã được hệ thống tự tạo theo phương thức thanh toán.</div>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary">Quay lại</a>
                <a href="{{ route('orders.index') }}" class="btn btn-success d-none" id="paymentBankComplete">Hoàn tất</a>
                <button type="submit" class="btn btn-primary {{ $initialMethod === 'bank_transfer' ? 'd-none' : '' }}" id="paymentCashSubmit" @disabled($initialMethod === 'bank_transfer')>Xác nhận đã thu tiền</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
@vite('resources/js/payment-create.js')
@endpush
