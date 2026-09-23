@extends('layouts.app')
@section('title', 'Tạo Hóa Đơn - Giặt Ủi Pro')
@section('page-title', 'Tạo hóa đơn')
@section('content')
<div class="card"><div class="card-body"><form action="{{ route('invoices.store') }}" method="POST">@csrf<h5 class="mb-4">Thông tin hóa đơn</h5><div class="row g-3"><div class="col-md-6"><label class="form-label">Đơn hàng</label><select class="form-select" name="order_id"><option value="">Chưa gắn đơn hàng</option></select></div><div class="col-md-6"><label class="form-label">Tổng tiền</label><input type="number" min="0" class="form-control" name="total" value="250000" required></div><div class="col-12"><label class="form-label">Ghi chú</label><textarea class="form-control" name="notes" rows="3"></textarea></div></div><div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">Hủy</a><button class="btn btn-primary">Lưu hóa đơn</button></div></form></div></div>
@endsection
