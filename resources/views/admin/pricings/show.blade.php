@extends('layouts.app')
@section('title', 'Chi Tiết Bảng Giá - Giặt Ủi Pro')
@section('page-title', 'Chi tiết bảng giá')
@section('content')
<div class="card"><div class="card-body">
    <h4>{{ $pricing->name }}</h4>
    <p class="text-muted">{{ $pricing->unit ?? 'kg' }} · {{ number_format($pricing->price) }} VNĐ/{{ $pricing->unit }}</p>
    @if($pricing->description)<p class="small text-muted mt-2">{{ $pricing->description }}</p>@endif
    <div class="mt-4">
        <a href="{{ route('pricings.index') }}" class="btn btn-outline-secondary">Quay lại</a>
        <a href="{{ route('pricings.edit', $pricing) }}" class="btn btn-primary">Chỉnh sửa</a>
    </div>
</div></div>
@endsection
