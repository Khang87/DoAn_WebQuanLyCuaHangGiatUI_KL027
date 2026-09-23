@extends('layouts.app')
@section('title', 'Ghi Nhận Thanh Toán - Giặt Ủi Pro')
@section('page-title', 'Ghi nhận thanh toán')
@section('content')
<div class="card"><div class="card-body"><form action="{{ route('payments.store') }}" method="POST">@csrf<h5 class="mb-4">Thông tin thanh toán</h5><div class="row g-3"><div class="col-md-6"><label class="form-label">Mã đơn hàng</label><select class="form-select" name="order_id"><option>#DH001 - Nguyễn Văn A</option><option>#DH002 - Trần Thị B</option></select></div><div class="col-md-6"><label class="form-label">Số tiền</label><input class="form-control" name="amount" placeholder="250,000 VNĐ" required></div><div class="col-md-6"><label class="form-label">Phương thức</label><select class="form-select" name="method"><option>Tiền mặt</option><option>Chuyển khoản</option><option>Ví điện tử</option></select></div></div><div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('payments.index') }}" class="btn btn-outline-secondary">Hủy</a><button class="btn btn-primary">Lưu thanh toán</button></div></form></div></div>
@endsection
