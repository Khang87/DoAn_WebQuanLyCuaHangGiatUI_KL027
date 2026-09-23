@extends('layouts.app')

@section('title', 'Chi tiết đơn hàng - Giặt Ủi Pro')
@section('page-title', 'Chi tiết')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('order-items.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Quay lại
    </a>
</div>

<div class="card">
    <div class="card-body">
        <h5 class="mb-3">{{ $item->item_name }}</h5>
        <table class="table table-borderless">
            <tr><td><strong>Đơn hàng</strong></td><td>#{{ $item->order?->code }}</td></tr>
            <tr><td><strong>Loại</strong></td><td>{{ $item->item_type }}</td></tr>
            <tr><td><strong>Đơn giá</strong></td><td>{{ number_format($item->price) }} VNĐ</td></tr>
            <tr><td><strong>Số lượng</strong></td><td>{{ $item->quantity }}</td></tr>
            <tr><td><strong>Thành tiền</strong></td><td><strong>{{ number_format($item->subtotal) }} VNĐ</strong></td></tr>
            <tr><td><strong>Ghi chú</strong></td><td>{{ $item->notes ?: '-' }}</td></tr>
        </table>
    </div>
</div>

<div class="d-flex gap-2">
    <a href="{{ route('order-items.edit', $item) }}" class="btn btn-warning">Sửa</a>
    <form action="{{ route('order-items.destroy', $item) }}" method="POST" onsubmit="return confirm('Xóa?')">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-danger">Xóa</button>
    </form>
</div>
@endsection
