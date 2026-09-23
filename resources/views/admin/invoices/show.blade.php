@extends('layouts.app')
@section('title', 'Chi Tiết Hóa Đơn - Giặt Ủi Pro')
@section('page-title', 'Chi tiết hóa đơn')
@section('content')
<div class="card"><div class="card-body"><div class="d-flex justify-content-between mb-4"><div><span class="text-muted">Số hóa đơn</span><h4>HD{{ str_pad($id, 3, '0', STR_PAD_LEFT) }}</h4></div><span class="badge-status badge-completed">Đã phát hành</span></div><div class="row g-4"><div class="col-md-6"><small class="text-muted">Khách hàng</small><div class="fw-semibold">Nguyễn Văn A</div></div><div class="col-md-6"><small class="text-muted">Mã đơn</small><div class="fw-semibold">#DH001</div></div><div class="col-md-6"><small class="text-muted">Dịch vụ</small><div class="fw-semibold">Giặt thường · 5kg đồ + 3 áo trắng</div></div><div class="col-md-6"><small class="text-muted">Tổng tiền</small><div class="fw-semibold">250,000 VNĐ</div></div></div><a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary mt-4">Quay lại</a></div></div>
@endsection
