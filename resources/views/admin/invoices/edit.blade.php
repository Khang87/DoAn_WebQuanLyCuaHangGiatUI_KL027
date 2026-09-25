@extends('layouts.app')
@section('title', 'Sửa Hóa Đơn - Sky Laundry')
@section('page-title', 'Sửa hóa đơn')
@section('content')
<div class="card"><div class="card-body"><form action="{{ route('invoices.update', $invoice) }}" method="POST">@csrf @method('PUT')
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Mã đơn hàng</label><input type="text" class="form-control" value="{{ $invoice->order?->code ?? 'Chưa có' }}" disabled></div>
        <div class="col-md-6"><label class="form-label">Tổng tiền</label><input type="number" class="form-control" name="total" value="{{ $invoice->total ?? 0 }}" min="0" required></div>
        <div class="col-md-6"><label class="form-label">Trạng thái</label><select class="form-select" name="status"><option value="unpaid" @selected($invoice->status === 'unpaid')">Chưa thanh toán</option><option value="paid" @selected($invoice->status === 'paid')">Đã thanh toán</option></select></div>
        <div class="col-12"><label class="form-label">Ghi chú</label><textarea class="form-control" name="notes" rows="3">{{ $invoice->notes ?? '' }}</textarea></div>
    </div>
    <button class="btn btn-primary mt-4">Lưu thay đổi</button>
</form></div></div>
@endsection
