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
            <input type="hidden" name="order_id" value="{{ $payment->DonHangID }}">
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Mã đơn hàng</label>
                    <input type="text" class="form-control" value="{{ $payment->donHang?->MaDonHang ?? 'Chưa có' }}" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Hóa đơn</label>
                    <input type="text" class="form-control" value="{{ $payment->donHang?->hoaDons?->first()?->MaHoaDon ?: 'Chưa liên kết' }}" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Số tiền thanh toán toàn bộ <span class="text-danger ms-1">*</span></label>
                    <input type="number" class="form-control" name="amount" value="{{ old('amount', (int) round($fullAmount)) }}" min="0" step="1000" required>
                    <div class="form-text">Số tiền phải bằng toàn bộ số tiền của hóa đơn; không hỗ trợ thanh toán một phần.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phương thức <span class="text-danger ms-1">*</span></label>
                    <select class="form-select" name="method" required>
                        <option value="cash" {{ $payment->PhuongThuc === 'Tiền mặt' ? 'selected' : '' }}>Tiền mặt</option>
                        <option value="bank_transfer" {{ $payment->PhuongThuc === 'Chuyển khoản' ? 'selected' : '' }}>Chuyển khoản / QR</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trạng thái <span class="text-danger ms-1">*</span></label>
                    <x-admin.status-select
                        name="status"
                        :options="\App\Enums\PaymentStatus::options()"
                        :selected="$payment->TrangThai"
                        class="form-select @error('status') is-invalid @enderror"
                        required
                    />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ngày thanh toán</label>
                    <input type="datetime-local" class="form-control" name="paid_at" value="{{ $payment->ThoiGian?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mã giao dịch</label>
                    <input type="text" class="form-control" name="transaction_code" value="{{ $payment->MaGiaoDich ?? '' }}" placeholder="Mã GD từ ngân hàng / ví">
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