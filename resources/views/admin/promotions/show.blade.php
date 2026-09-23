@extends('layouts.app')
@section('title', 'Chi Tiết Khuyến Mãi - Giặt Ủi Pro')
@section('page-title', 'Chi tiết khuyến mãi')
@section('content')
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <span class="text-muted">Mã khuyến mãi</span>
            <h4 class="mb-0 text-primary">{{ $promotion->code }}</h4>
        </div>
        <span class="badge-status badge-{{ $promotion->status === 'active' ? 'completed' : 'pending' }}">{{ $promotion->status === 'active' ? 'Đang áp dụng' : 'Hết hạn' }}</span>
    </div>
    <div class="row g-4">
        <div class="col-12"><div class="small text-muted">Tên khuyến mãi</div><div class="fw-semibold">{{ $promotion->name }}</div></div>
        <div class="col-md-6"><div class="small text-muted">Chiết khấu</div><div class="fw-semibold">{{ $promotion->discount }}</div></div>
        <div class="col-md-6"><div class="small text-muted">Ngày tạo</div><div class="fw-semibold">{{ $promotion->created_at?->format('d/m/Y') }}</div></div>
        <div class="col-md-6"><div class="small text-muted">Hết hạn</div><div class="fw-semibold">{{ $promotion->expires_at?->format('d/m/Y') ?? 'Không thời hạn' }}</div></div>
    </div>
    <div class="mt-4">
        <a href="{{ route('promotions.index') }}" class="btn btn-outline-secondary">Quay lại</a>
        <a href="{{ route('promotions.edit', $promotion) }}" class="btn btn-primary">Chỉnh sửa</a>
    </div>
</div></div>
@endsection
