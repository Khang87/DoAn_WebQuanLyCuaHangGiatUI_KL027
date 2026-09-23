@extends('layouts.app')
@section('title', 'Sửa Thanh Toán - Giặt Ủi Pro')
@section('page-title', 'Sửa thanh toán')
@section('content')
<div class="card"><div class="card-body"><form action="{{ route('payments.update', $id) }}" method="POST">@csrf @method('PUT')<h5 class="mb-4">Cập nhật thanh toán</h5><div class="row g-3"><div class="col-md-6"><label class="form-label">Số tiền</label><input class="form-control" name="amount" value="250,000 VNĐ"></div><div class="col-md-6"><label class="form-label">Phương thức</label><select class="form-select" name="method"><option selected>Tiền mặt</option><option>Chuyển khoản</option></select></div></div><button class="btn btn-primary mt-4">Lưu thay đổi</button></form></div></div>
@endsection
