@extends('layouts.app')

@section('title', 'Ghi nhận thanh Toán - Sky Laundry')
@section('page-title', 'Ghi nhận thanh toán')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Thông tin thanh toán</h5>
            <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('payments.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Đơn hàng</label>
                    <select class="form-select" name="order_id">
                        <option value="">Chọn đơn hàng</option>
                        @foreach($orders as $order)
                        <option value="{{ $order->id }}" {{ $preselectedOrder && $preselectedOrder->id == $order->id ? 'selected' : '' }}>
                            {{ $order->code }} - {{ $order->customer?->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Hóa đơn (tự động lấy từ đơn hàng)</label>
                    <select class="form-select" name="invoice_id">
                        <option value="">Chọn hóa đơn (tùy chọn)</option>
                        @foreach($invoices as $inv)
                        <option value="{{ $inv->id }}" {{ $preselectedInvoice && $preselectedInvoice->id == $inv->id ? 'selected' : '' }}>
                            {{ $inv->code }} - {{ $inv->order?->customer?->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Số tiền <span class="text-danger ms-1">*</span></label>
                    <input type="number" class="form-control" name="amount" placeholder="250000" min="0" step="1000" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phương thức <span class="text-danger ms-1">*</span></label>
                    <select class="form-select" name="method" required>
                        <option value="cash">Tiền mặt</option>
                        <option value="bank_transfer">Chuyển khoản / QR</option>
                        <option value="momo">Ví MoMo</option>
                        <option value="credit_card">Thẻ ATM / Credit</option>
                        <option value="e_wallet">Ví điện tử</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trạng thái <span class="text-danger ms-1">*</span></label>
                    <select class="form-select" name="status" required>
                        <option value="paid">Đã thanh toán</option>
                        <option value="partial">Một phần</option>
                        <option value="pending">Chờ thanh toán</option>
                        <option value="failed">Thất bại</option>
                        <option value="refunded">Đã hoàn tiền</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ngày thanh toán</label>
                    <input type="datetime-local" class="form-control" name="paid_at" value="{{ now()->format('Y-m-d\TH:i') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mã giao dịch</label>
                    <input type="text" class="form-control" name="transaction_code" placeholder="Mã GD từ ngân hàng / ví">
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
