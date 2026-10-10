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
            <input type="hidden" name="order_id" value="{{ $invoice->DonHangID }}">
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Mã đơn hàng</label>
                    <input type="text" class="form-control" value="{{ $invoice->donHang?->MaDonHang ?? 'Chưa có' }}" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ngày lập <span class="text-danger ms-1">*</span></label>
                    <input type="date" class="form-control" name="invoice_date" value="{{ $invoice->NgayLap?->format('Y-m-d') ?? now()->toDateString() }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tạm tính <span class="text-danger ms-1">*</span></label>
                    <input type="number" class="form-control" name="total_amount" value="{{ old('total_amount', (int) round((float) $invoice->TongTien)) }}" min="0" step="1000" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Giảm giá</label>
                    <input type="number" class="form-control" name="discount_amount" value="{{ old('discount_amount', (int) round((float) $invoice->GiamGia)) }}" min="0" step="1000">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Phí giao hàng</label>
                    <input type="number" class="form-control" name="delivery_fee" value="{{ old('delivery_fee', (int) round((float) $invoice->PhiGiaoHang)) }}" min="0" step="1000">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trạng thái</label>
                    @if($invoice->isPaid())
                        <input type="text" class="form-control" value="{{ $invoice->TrangThai }}" disabled>
                    @else
                        @php($invoiceStatusOptions = \App\Enums\InvoiceStatus::options())
                        @unset($invoiceStatusOptions[\App\Enums\InvoiceStatus::Paid->value])
                        <x-admin.status-select
                            name="status"
                            :options="$invoiceStatusOptions"
                            :selected="$invoice->TrangThai"
                            class="form-select @error('status') is-invalid @enderror"
                        />
                    @endif
                </div>
                <div class="col-12">
                    <label class="form-label">Ghi chú</label>
                    <textarea class="form-control" name="notes" rows="3">{{ $invoice->GhiChu ?? '' }}</textarea>
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