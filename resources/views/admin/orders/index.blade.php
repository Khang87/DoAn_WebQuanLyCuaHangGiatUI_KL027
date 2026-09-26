@extends('layouts.app')

@section('title', 'Quản lý Đơn Hàng - Sky Laundry')
@section('page-title', 'Quản lý Đơn Hàng')

@section('content')
@php
    $orderStatusLabels = [
        'pending' => 'Chờ tiếp nhận',
        'received' => 'Đã nhận đồ',
        'sorting' => 'Đang phân loại',
        'processing' => 'Đang giặt / Xử lý',
        'washed' => 'Đã giặt xong',
        'delivering' => 'Đang giao đồ',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Đã hủy',
    ];
@endphp
<!-- Page Actions -->
<div class="order-toolbar d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="{{ route('orders.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i>Tạo đơn hàng mới
        </a>
    </div>
</div>
<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-5">
        <div class="input-group shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control border-start-0 py-2 ps-2" placeholder="Tìm kiếm đơn hàng..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-md-4">
        <select name="status" class="form-select shadow-sm rounded-3 py-2" style="min-width: 220px;" onchange="this.form.submit()">
            <option value="">-- Tất cả trạng thái --</option>
            @foreach($statusFlow as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
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
                        @php
                            $currentSortBy = request('sort_by');
                            $currentSortOrder = request('sort_order', 'desc');
                            $nextOrderCode = ($currentSortBy === 'code' && $currentSortOrder === 'asc') ? 'desc' : 'asc';
                            $nextOrderTotal = ($currentSortBy === 'total_amount' && $currentSortOrder === 'asc') ? 'desc' : 'asc';
                            $nextOrderCreated = ($currentSortBy === 'created_at' && $currentSortOrder === 'asc') ? 'desc' : 'asc';
                        @endphp
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'code', 'sort_order' => $nextOrderCode]) }}" class="text-dark text-decoration-none">
                                Mã Đơn
                                @if($currentSortBy === 'code') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>Khách hàng</th>
                        <th>Dịch vụ</th>
                        <th>Số lượng</th>
                        <th>Ghi chú khách Hàng</th>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'total_amount', 'sort_order' => $nextOrderTotal]) }}" class="text-dark text-decoration-none">
                                Tổng tiền
                                @if($currentSortBy === 'total_amount') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>Trạng thái</th>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => $nextOrderCreated]) }}" class="text-dark text-decoration-none">
                                Ngày tạo
                                @if($currentSortBy === 'created_at') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                    <tr>
                        <td><strong>{{ $order->code }}</strong></td>
                        <td>
                            @php
                                $randomImage = 'assets/images/user_' . (($order->customer->id % 8) + 1) . '.jpg';
                                $avatarUrl = (!empty($order->customer->avatar) && file_exists(public_path($order->customer->avatar))) 
                                           ? asset($order->customer->avatar) 
                                           : asset($randomImage);
                            @endphp
                            <div class="d-flex align-items-center">
                                @if($order->customer)
                                    <img src="{{ $avatarUrl }}" alt="Avatar" class="rounded-circle me-2 avatar-cover" style="width: 32px; height: 32px;">
                                @else
                                    <img src="{{ asset('assets/images/user_1.jpg') }}" alt="Avatar" class="rounded-circle me-2 avatar-cover" style="width: 32px; height: 32px;">
                                @endif
                                <div>
                                    <div class="fw-semibold">{{ $order->customer?->name ?: '-' }}</div>
                                    <small class="text-muted">{{ $order->customer?->phone ?: 'Chưa có số điện thoại' }}</small>
                                </div>
                            </div>
                        </td>
                         <td>{{ $order->items->pluck('service.name')->filter()->join(', ') ?: ($order->service?->name ?: '-') }}</td>
                        <td>
                            <strong>
                                 {{ number_format((float)($order->items->sum('quantity') ?: ($order->quantity_items ?? $order->weight_kg ?? 0))) }}
                                 {{ $order->service?->unit ?? 'món' }}
                            </strong>
                        </td>
                        <td><small>{{ $order->notes ?: 'Không có ghi chú' }}</small></td>
                        <td><strong>{{ number_format($order->total_amount) }} VNĐ</strong></td>
                        <td>
                             @php $status = $orderStatusLabels[$order->status] ?? $orderStatusLabels['pending']; @endphp
                             @if($order->status === 'completed')
                                 <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>{{ $status }}</span>
                             @elseif($order->status === 'cancelled')
                                 <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-x-circle me-1"></i>{{ $status }}</span>
                             @elseif($order->status === 'delivering')
                                 <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2 rounded-pill"><i class="fas fa-truck me-1"></i>{{ $status }}</span>
                             @elseif($order->status === 'processing')
                                 <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill"><i class="fas fa-washer me-1"></i>{{ $status }}</span>
                             @elseif($order->status === 'washed')
                                 <span class="badge bg-info-subtle text-info border border-info px-3 py-2 rounded-pill"><i class="fas fa-tshirt-pocket me-1"></i>{{ $status }}</span>
                             @else
                                 <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill"><i class="fas fa-clock me-1"></i>{{ $status }}</span>
                             @endif
                        </td>
                        <td>{{ $order->created_at?->format('d/m/Y') }}</td>
                        <td>
                            <div class="d-flex gap-2">
                                @if(!$order->invoice && in_array($order->status, ['completed', 'washed', 'delivering']))
                                    <a href="{{ route('invoices.create', ['order_id' => $order->id]) }}" class="btn btn-sm btn-outline-primary" title="Tạo hóa đơn" data-bs-toggle="tooltip">
                                        <i class="fas fa-file-invoice"></i>
                                    </a>
                                @elseif($order->invoice)
                                    <a href="{{ route('invoices.show', $order->invoice->id) }}" class="btn btn-sm btn-outline-info" title="Xem hóa đơn" data-bs-toggle="tooltip">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                @endif
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
                                @if($order->is_locked)
                                    <span class="badge bg-secondary text-white px-3 py-2 rounded-pill d-flex align-items-center" title="Đã quyết toán">
                                        <i class="bi bi-lock me-1"></i>Đã quyết toán
                                    </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Chưa có đơn hàng nào</td>
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
