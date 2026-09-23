@extends('layouts.app')
@section('title', 'Chi Tiết Bảng Giá - Giặt Ủi Pro')
@section('page-title', 'Chi tiết bảng giá')
@section('content')
<div class="card"><div class="card-body"><h4>Giặt thường</h4><p class="text-muted">Đồ thường · 25,000 VNĐ/kg</p><a href="{{ route('pricings.index') }}" class="btn btn-outline-secondary">Quay lại</a><a href="{{ route('pricings.edit', $id) }}" class="btn btn-primary">Chỉnh sửa</a></div></div>
@endsection
