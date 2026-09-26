@extends('layouts.app')

@section('title', 'Quản lý Đơn hàng - Sky Laundry')
@section('page-title', 'Quản lý Đơn hàng')

@section('content')
<!-- Page Actions: nút "Thêm" luôn nằm góc trên bên trái -->
<div class="page-toolbar">
    <a href="{{ route('orders.create') }}" class="btn btn-create">
        <i class="bi bi-plus-lg"></i>Thêm đơn hàng
    </a>
    <p class="text-muted page-toolbar__desc">Theo dõi toàn bộ đơn giặt của khách hàng, từ lúc tiếp nhận đến khi hoàn tất giao trả.</p>
</div>
<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-auto flex-grow-1">
        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control form-control-sm border-start-0 ps-2" placeholder="Tìm kiếm đơn hàng..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <x-admin.status-select
            name="status"
            id="filter-status"
            :options="$statusFlow ?? \App\Enums\OrderStatus::options()"
            placeholder="-- Tất cả trạng thái --"
            class="form-select form-select-sm filter-select shadow-sm rounded-3"
            submit
        />
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="sort" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="latest" @selected(request('sort', 'latest') === 'latest')>Mới nhất</option>
            <option value="oldest" @selected(request('sort') === 'oldest')>Cũ nhất</option>
            <option value="total_desc" @selected(request('sort') === 'total_desc')>Tổng tiền cao → thấp</option>
            <option value="total_asc" @selected(request('sort') === 'total_asc')>Tổng tiền thấp → cao</option>
            <option value="code_asc" @selected(request('sort') === 'code_asc')>Mã đơn A → Z</option>
            <option value="code_desc" @selected(request('sort') === 'code_desc')>Mã đơn Z → A</option>
        </select>
    </div>
</form>

<!-- Orders Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th class="fw-bold text-dark">Mã đơn hàng</th>
                        <th class="fw-bold text-dark">Khách hàng</th>
                        <th class="fw-bold text-dark">Số điện thoại</th>
                        <th class="fw-bold text-dark">Dịch vụ</th>
                        <th class="fw-bold text-dark">Số lượng</th>
                        <th class="fw-bold text-dark">Ghi chú khách hàng</th>
                        <th class="fw-bold text-dark">Tổng tiền</th>
                        <th class="fw-bold text-dark">Trạng thái</th>
                        <th class="fw-bold text-dark">Ngày tạo</th>
                        <th class="fw-bold text-dark">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                    <tr>
                        <td class="text-dark">{{ $order->code }}</td>
                        <td>
                            @php
                                $customer = $order->customer;
                                $avatarUrl = asset('assets/images/user_' . ((($customer->id ?? 0) % 8) + 1) . '.jpg');
                            @endphp
                            <div class="d-flex align-items-center">
                                @if($order->customer)
                                    <img src="{{ $avatarUrl }}" alt="Avatar" class="rounded-circle me-2 avatar-cover" style="width: 32px; height: 32px;">
                                @else
                                    <img src="{{ asset('assets/images/user_1.jpg') }}" alt="Avatar" class="rounded-circle me-2 avatar-cover" style="width: 32px; height: 32px;">
                                @endif
                                <div>
                                    <div class="text-dark">{{ $order->customer?->name ?: '-' }}</div>
                                    <small class="text-muted">{{ $order->customer?->phone ?: 'Chưa có số điện thoại' }}</small>
                                </div>
                            </div>
                        </td>
                        <td class="text-dark">{{ $order->customer?->phone ?: '-' }}</td>
                        <td class="text-dark">{{ $order->items->pluck('service.name')->filter()->join(', ') ?: ($order->service?->name ?: '-') }}</td>
                        <td class="text-dark">
                             {{ number_format((float)($order->items->sum('quantity') ?: ($order->quantity_items ?? $order->weight_kg ?? 0))) }}
                             {{ $order->service?->unit ?? 'món' }}
                        </td>
                        <td><small class="text-muted">{{ $order->notes ?: 'Không có ghi chú' }}</small></td>
                        <td class="text-dark">{{ number_format($order->total_amount) }} VNĐ</td>
                        <td>
                            <x-admin.status-badge :status="$order->status" :enum="\App\Enums\OrderStatus::class" />
                        </td>
                        <td class="text-dark">{{ $order->created_at?->format('d/m/Y') }}</td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('orders.show', $order) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                @if($order->can_edit)
                                    <a href="{{ route('orders.edit', $order) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                @endif
                                @if($order->can_delete)
                                    <form action="{{ route('orders.destroy', $order) }}" method="POST" class="d-inline" id="deleteOrderForm_{{ $order->id }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endif
                                @if(!$order->can_edit && !$order->can_delete)
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary px-3 py-2 rounded-pill d-flex align-items-center" title="Đã quyết toán">
                                        <i class="bi bi-lock me-1"></i>Đã quyết toán
                                    </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">Chưa có đơn hàng nào</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($orders->hasPages())
<!-- Pagination -->
<nav class="mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">
            Hiển thị {{ $orders->firstItem() }} - {{ $orders->lastItem() }} của {{ $orders->total() }} đơn hàng
        </div>
        <ul class="pagination mb-0">
            @if ($orders->onFirstPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $orders->appends(request()->query())->url($orders->currentPage() - 1) }}"><i class="bi bi-chevron-left"></i></a></li>
            @endif
            @foreach ($orders->getUrlRange(max(1, $orders->currentPage() - 2), min($orders->lastPage(), $orders->currentPage() + 2)) as $page => $url)
                @if ($page == $orders->currentPage())
                    <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $orders->appends(request()->query())->url($page) }}">{{ $page }}</a></li>
                @endif
            @endforeach
            @if ($orders->onLastPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $orders->appends(request()->query())->url($orders->currentPage() + 1) }}"><i class="bi bi-chevron-right"></i></a></li>
            @endif
        </ul>
    </div>
</nav>
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[id^="deleteOrderForm_"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (typeof Swal === 'undefined') {
                    if (confirm('Bạn có chắc muốn xóa?')) {
                        form.submit();
                    }
                    return;
                }

                Swal.fire({
                    title: 'Xóa đơn hàng?',
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