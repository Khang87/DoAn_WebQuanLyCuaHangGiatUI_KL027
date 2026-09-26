@extends('layouts.app')
@section('title', 'Sửa thanh toán - Sky Laundry')
@section('page-title', 'Sửa thanh toán')
@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Sửa thanh toán</h5>
            <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('payments.update', $payment) }}" method="POST">
            @csrf @method('PUT')
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Mã đơn hàng</label>
                    <input type="text" class="form-control" value="{{ $payment->order?->code ?? 'Chưa có' }}" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Hóa đơn</label>
                    <input type="text" class="form-control" value="{{ $payment->invoice?->code ?: 'Chưa liên kết' }}" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Số tiền <span class="text-danger ms-1">*</span></label>
                    <input type="number" class="form-control" name="amount" value="{{ old('amount', (int) round((float) $payment->amount)) }}" min="0" step="1000" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phương thức <span class="text-danger ms-1">*</span></label>
                    <select class="form-select" name="method" required>
                        <option value="cash" {{ $payment->method === 'cash' ? 'selected' : '' }}>Tiền mặt</option>
                        <option value="bank_transfer" {{ $payment->method === 'bank_transfer' ? 'selected' : '' }}>Chuyển khoản / QR</option>
                        <option value="momo" {{ $payment->method === 'momo' ? 'selected' : '' }}>Ví MoMo</option>
                        <option value="credit_card" {{ $payment->method === 'credit_card' ? 'selected' : '' }}>Thẻ ATM / Credit</option>
                        <option value="e_wallet" {{ $payment->method === 'e_wallet' ? 'selected' : '' }}>Ví điện tử</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trạng thái <span class="text-danger ms-1">*</span></label>
                    <x-admin.status-select
                        name="status"
                        :options="\App\Enums\PaymentStatus::options()"
                        :selected="$payment->status"
                        class="form-select @error('status') is-invalid @enderror"
                        required
                    />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ngày thanh toán</label>
                    <input type="datetime-local" class="form-control" name="paid_at" value="{{ $payment->paid_at?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mã giao dịch</label>
                    <input type="text" class="form-control" name="transaction_code" value="{{ $payment->transaction_code ?? '' }}" placeholder="Mã GD từ ngân hàng / ví">
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary">Hủy</a>
                <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
            </div>
        </form>
    </div>
</div>
@endsection