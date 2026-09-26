@extends('layouts.app')
@section('title', 'Chi tiết bảng Giá - Sky Laundry')
@section('page-title', 'Chi tiết bảng giá')
@section('content')
<div class="container py-2"><div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-start mb-4"><div><span class="text-muted">Bảng giá</span><h4 class="mb-0">{{ $pricing->service?->name ?: $pricing->name ?: 'Bảng giá dịch vụ' }}</h4></div>@if($pricing->status === 'active')<span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill">Đang áp dụng</span>@else<span class="badge bg-secondary-subtle text-secondary border border-secondary px-3 py-2 rounded-pill">Tạm ngừng</span>@endif</div>
    <div class="row g-4"><div class="col-md-6"><div class="small text-muted">Dịch vụ</div><div class="fw-semibold mb-3">{{ $pricing->service?->name ?: $pricing->name ?: '—' }}</div><div class="small text-muted">Loại đồ giặt</div><div class="fw-semibold">{{ $pricing->garment?->name ?: $pricing->garment_name ?: '—' }}</div></div><div class="col-md-6"><div class="small text-muted">Đơn vị tính</div><div class="fw-semibold mb-3">{{ $pricing->unit ?: 'kg' }}</div><div class="small text-muted">Ngày áp dụng</div><div class="fw-semibold">{{ $pricing->effective_date?->format('d/m/Y') ?: $pricing->created_at?->format('d/m/Y') ?: '—' }}</div></div></div>
    <div class="bg-light rounded-3 p-4 my-4 text-center"><div class="small text-muted">Đơn giá</div><div class="display-6 fw-bold text-primary">{{ number_format($pricing->price) }} VNĐ</div><div class="text-muted">/ {{ $pricing->unit ?: 'kg' }}</div></div>
    @if($pricing->description)<div><strong>Mô tả:</strong><p class="text-muted mb-0">{{ $pricing->description }}</p></div>@endif
    <div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('pricings.index') }}" class="btn btn-outline-secondary">Quay lại</a><a href="{{ route('pricings.edit', $pricing) }}" class="btn btn-primary">Chỉnh sửa</a></div>
</div></div></div>
@endsection