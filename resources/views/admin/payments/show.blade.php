@extends('layouts.app')
@section('title', 'Chi Tiết Thanh Toán - Giặt Ủi Pro')
@section('page-title', 'Chi tiết thanh toán')
@section('content')
<div class="card"><div class="card-body"><h4>TT{{ str_pad($id, 3, '0', STR_PAD_LEFT) }}</h4><p class="mb-1">Đơn hàng: <strong>#DH001</strong></p><p class="mb-1">Số tiền: <strong>250,000 VNĐ</strong></p><p>Phương thức: Tiền mặt</p><a href="{{ route('payments.index') }}" class="btn btn-outline-secondary">Quay lại</a></div></div>
@endsection
