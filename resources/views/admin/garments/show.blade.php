@extends('layouts.app')
@section('title', 'Chi Tiết Loại Đồ - Giặt Ủi Pro')
@section('page-title', 'Chi tiết loại đồ')
@section('content')
<div class="card"><div class="card-body"><h4>Áo sơ mi</h4><p class="text-muted">Tính theo cái · 15,000 VNĐ/cái</p><a href="{{ route('garments.index') }}" class="btn btn-outline-secondary">Quay lại</a><a href="{{ route('garments.edit', $id) }}" class="btn btn-primary">Chỉnh sửa</a></div></div>
@endsection
