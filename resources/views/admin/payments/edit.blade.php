@extends('layouts.app')
@section('title', 'Sửa Thanh Toán - Giặt Ủi Pro')
@section('page-title', 'Sửa thanh toán')
@section('content')
<div class="card"><div class="card-body"><form action="{{ route('payments.update', $payment) }}" method="POST">@csrf @method('PUT')
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Mã đơn hàng</label><input type="text" class="form-control" value="{{ $payment->order?->code ?? 'Chưa có' }}" disabled></div>
        <div class="col-md-6"><label class="form-label">Số tiền</label><input type="number" class="form-control" name="amount" value="{{ $payment->amount }}" min="0" required></div>
        <div class="col-md-6"><label class="form-label">Phương thức</label><select class="form-select" name="method"><option value="cash" @selected($payment->method === 'cash')">Tiền mặt</option><option value="transfer" @selected($payment->method === 'transfer')">Chuyển khoản</option><option value="wallet" @selected($payment->method === 'wallet')">Ví điện tử</option></select></div>
        <div class="col-md-6"><label class="form-label">Trạng thái</label><select class="form-select" name="status"><option value="pending" @selected($payment->status === 'pending')">Chưa thanh toán</option><option value="completed" @selected($payment->status === 'completed')">Đã thanh toán</option></select></div>
    </div>
    <button class="btn btn-primary mt-4">Lưu thay đổi</button>
</form></div></div>
@endsection
