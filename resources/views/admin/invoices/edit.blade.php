@extends('layouts.app')
@section('title', 'Sửa Hóa Đơn - Giặt Ủi Pro')
@section('page-title', 'Sửa hóa đơn')
@section('content')
<div class="card"><div class="card-body"><form action="{{ route('invoices.update', $id) }}" method="POST">@csrf @method('PUT')<h5 class="mb-4">Cập nhật hóa đơn</h5><label class="form-label">Tổng tiền</label><input class="form-control" name="total" value="250,000 VNĐ"><button class="btn btn-primary mt-4">Lưu thay đổi</button></form></div></div>
@endsection
