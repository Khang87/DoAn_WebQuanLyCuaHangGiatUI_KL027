@extends('layouts.app')

@section('title', 'Danh sách chi tiết đơn hàng - Giặt Ủi Pro')
@section('page-title', 'Chi tiết đơn hàng')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Quay lại Đơn hàng
        </a>
    </div>
    <form action="{{ route('order-items.index') }}" method="GET" class="d-flex gap-2">
        <div class="input-group" style="width: 200px;">
            <input type="text" name="order_id" class="form-control" placeholder="Mã đơn hàng..." value="{{ request('order_id') }}">
        </div>
        <a href="{{ route('order-items.index') }}" class="btn btn-outline-secondary">Xóa</a>
    </form>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>ID</th><th>Đơn hàng</th><th>Mặt hàng</th><th>Loại</th><th>Đơn giá</th><th>SL</th><th>Thành tiền</th><th>Ghi chú</th><th>Thao tác</th></tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                    <tr>
                        <td>{{ $item->id }}</td>
                        <td><a href="{{ route('orders.show', $item->order_id) }}">{{ $item->order?->code }}</a></td>
                        <td><strong>{{ $item->item_name }}</strong></td>
                        <td>{{ $item->item_type }}</td>
                        <td>{{ number_format($item->price) }} VNĐ</td>
                        <td>{{ $item->quantity }}</td>
                        <td><strong>{{ number_format($item->subtotal) }} VNĐ</strong></td>
                        <td>{{ $item->notes ?: '-' }}</td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('order-items.edit', $item) }}" class="btn btn-sm btn-outline-warning">Sửa</a>
                                <form action="{{ route('order-items.destroy', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Xóa?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">Chưa có chi tiết</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($items->hasPages())
<div class="mt-3">
    {{ $items->appends(request()->query())->links('pagination::bootstrap-5') }}
</div>
@endif
@endsection
