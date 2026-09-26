@extends('layouts.app')

@section('title', 'Danh sách chi tiết đơn hàng - Sky Laundry')
@section('page-title', 'Chi tiết đơn hàng')

@section('content')
<div class="page-toolbar">
    <div>
        <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Quay lại Đơn hàng
        </a>
    </div>
    <p class="text-muted page-toolbar__desc">Danh sách chi tiết từng món đồ giặt trong các đơn hàng, phục vụ đối soát khi giao trả.</p>
</div>
<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12">
        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="order_id" class="form-control form-control-sm border-start-0 ps-2" placeholder="Mã đơn hàng..." value="{{ request('order_id') }}">
        </div>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr><th>STT</th><th>Mã mặt hàng</th><th>Đơn hàng</th><th>Mặt hàng</th><th>Loại</th><th>Đơn giá</th><th>SL</th><th>Thành tiền</th><th>Ghi chú</th><th>Thao tác</th></tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $item->code ?? 'CT' . str_pad($item->id, 4, '0', STR_PAD_LEFT) }}</td>
                        <td><span class="text-dark">{{ $item->order?->code }}</span></td>
                        <td><strong>{{ $item->item_name }}</strong></td>
                        <td>{{ $item->item_type }}</td>
                        <td>{{ number_format($item->price) }} VNĐ</td>
                        <td>{{ $item->quantity }}</td>
                        <td><strong>{{ number_format($item->subtotal) }} VNĐ</strong></td>
                        <td>{{ $item->notes ?: '-' }}</td>
                        <td>
                            <div class="d-flex gap-2">
                                @if(! $item->order?->isLocked())
                                    <a href="{{ route('order-items.edit', $item) }}" class="btn btn-sm btn-outline-warning">Sửa</a>
                                    <form action="{{ route('order-items.destroy', $item) }}" method="POST" class="d-inline" id="deleteOrderItemForm_{{ $item->id }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
                                    </form>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary px-3 py-2 rounded-pill" title="Đơn hàng đã quyết toán nên không thể sửa hoặc xóa">
                                        <i class="bi bi-lock me-1"></i>Đã quyết toán
                                    </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="10" class="text-center text-muted py-4">Chưa có chi tiết</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($items->hasPages())
<nav class="mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">Hiển thị {{ $items->firstItem() }} - {{ $items->lastItem() }} của {{ $items->total() }} chi tiết</div>
        <ul class="pagination mb-0">
            @if ($items->onFirstPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $items->appends(request()->query())->url($items->currentPage() - 1) }}"><i class="bi bi-chevron-left"></i></a></li>
            @endif
            @foreach ($items->getUrlRange(max(1, $items->currentPage() - 2), min($items->lastPage(), $items->currentPage() + 2)) as $page => $url)
                @if ($page == $items->currentPage())
                    <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $items->appends(request()->query())->url($page) }}">{{ $page }}</a></li>
                @endif
            @endforeach
            @if ($items->onLastPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $items->appends(request()->query())->url($items->currentPage() + 1) }}">{{ $page }}</a></li>
            @endif
        </ul>
    </div>
</nav>
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[id^="deleteOrderItemForm_"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (typeof Swal === 'undefined') {
                    if (confirm('Xóa?')) {
                        form.submit();
                    }
                    return;
                }

                Swal.fire({
                    title: 'Xóa chi tiết đơn hàng?',
                    text: 'Hành động này không thể hoàn tác.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Xóa',
                    cancelButtonText: 'Hủy'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    });
</script>
@endpush