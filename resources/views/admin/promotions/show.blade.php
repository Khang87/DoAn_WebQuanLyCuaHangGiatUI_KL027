@extends('layouts.app')
@section('title', 'Chi Tiết Khuyến Mãi - Giặt Ủi Pro')
@section('page-title', 'Chi tiết khuyến mãi')
@section('content')
<div class="card"><div class="card-body"><h4>GIAT10</h4><p>Giảm 10% đơn từ 200,000 VNĐ</p><p class="text-muted">Hết hạn: 30/09/2026</p><a href="{{ route('promotions.index') }}" class="btn btn-outline-secondary">Quay lại</a><a href="{{ route('promotions.edit', $id) }}" class="btn btn-primary">Chỉnh sửa</a></div></div>
@endsection
