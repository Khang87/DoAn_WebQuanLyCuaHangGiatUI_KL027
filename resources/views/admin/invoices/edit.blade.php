@extends('layouts.app')
@section('title', 'Sửa hóa Đơn - Sky Laundry')
@section('page-title', 'Sửa hóa đơn')
@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Sửa hóa đơn</h5>
            <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('invoices.update', $invoice) }}" method="POST">
            @csrf @method('PUT')
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Mã đơn hàng</label>
                    <input type="text" class="form-control" value="{{ $invoice->order?->code ?? 'Chưa có' }}" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ngày lập <span class="text-danger ms-1">*</span></label>
                    <input type="date" class="form-control" name="invoice_date" value="{{ $invoice->invoice_date?->format('Y-m-d') ?? now()->toDateString() }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tạm tính <span class="text-danger ms-1">*</span></label>
                    <input type="number" class="form-control" name="total_amount" value="{{ $invoice->total_amount }}" min="0" step="1000" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Giảm giá</label>
                    <input type="number" class="form-control" name="discount_amount" value="{{ $invoice->discount_amount }}" min="0" step="1000">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Phí giao hàng</label>
                    <input type="number" class="form-control" name="delivery_fee" value="{{ $invoice->delivery_fee }}" min="0" step="1000">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trạng thái</label>
                    <select class="form-select" name="status">
                        <option value="unpaid" {{ $invoice->status === 'unpaid' ? 'selected' : '' }}>Chờ thanh toán</option>
                        <option value="partial" {{ $invoice->status === 'partial' ? 'selected' : '' }}>Thanh toán một phần</option>
                        <option value="paid" {{ $invoice->status === 'paid' ? 'selected' : '' }}>Đã thanh toán</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Ghi chú</label>
                    <textarea class="form-control" name="notes" rows="3">{{ $invoice->notes ?? '' }}</textarea>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">Hủy</a>
                <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
            </div>
        </form>
    </div>
</div>
@endsection