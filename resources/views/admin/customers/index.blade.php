@extends('layouts.app')

@section('title', 'Quản Lý Khách Hàng - Sky Laundry')
@section('page-title', 'Quản Lý Khách Hàng')

@section('content')
<!-- Page Actions -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="{{ route('customers.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus me-2"></i>Thêm Khách Hàng
        </a>
    </div>
</div>
<form method="GET" action="{{ url()->current() }}" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-5">
        <div class="input-group shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control border-start-0 py-2 ps-2" placeholder="Tìm kiếm khách hàng..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-md-3">
        <select name="type" class="form-select shadow-sm rounded-3 py-2" style="min-width: 200px;" onchange="this.form.submit()">
            <option value="">-- Tất cả hạng --</option>
            <option value="VIP" {{ request('type') === 'VIP' ? 'selected' : '' }}>VIP</option>
            <option value="Thường" {{ request('type') === 'Thường' ? 'selected' : '' }}>Thường</option>
            <option value="Mới" {{ request('type') === 'Mới' ? 'selected' : '' }}>Mới</option>
        </select>
    </div>
</form>

<!-- Customers Table -->
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
                            $nextOrderName = ($currentSortBy === 'name' && $currentSortOrder === 'asc') ? 'desc' : 'asc';
                            $nextOrderPoints = ($currentSortBy === 'points' && $currentSortOrder === 'asc') ? 'desc' : 'asc';
                            $nextOrderCreated = ($currentSortBy === 'created_at' && $currentSortOrder === 'asc') ? 'desc' : 'asc';
                        @endphp
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'code', 'sort_order' => $nextOrderCode]) }}" class="text-dark text-decoration-none">
                                Mã khách hàng
                                @if($currentSortBy === 'code') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => $nextOrderName]) }}" class="text-dark text-decoration-none">
                                Khách Hàng
                                @if($currentSortBy === 'name') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>Email</th>
                        <th>Số Điện Thoại</th>
                        <th>Địa Chỉ</th>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'points', 'sort_order' => $nextOrderPoints]) }}" class="text-dark text-decoration-none">
                                Điểm Tích Lũy
                                @if($currentSortBy === 'points') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>Loại</th>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => $nextOrderCreated]) }}" class="text-dark text-decoration-none">
                                Ngày Đăng Ký
                                @if($currentSortBy === 'created_at') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                    <tr>
                        <td><strong>{{ $customer->code }}</strong></td>
                        <td>
                            <div class="d-flex align-items-center">
                                @php
                                    $randomImage = 'assets/images/user_' . (($customer->id % 8) + 1) . '.jpg';
                                    $avatarUrl = (!empty($customer->avatar) && file_exists(public_path($customer->avatar))) 
                                               ? asset($customer->avatar) 
                                               : asset($randomImage);
                                @endphp
                                <img src="{{ $avatarUrl }}" alt="Avatar" class="rounded-circle me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                <div class="fw-semibold">{{ $customer->name }}</div>
                            </div>
                        </td>
                        <td>{{ $customer->email ?: '-' }}</td>
                        <td>{{ $customer->phone ?: '-' }}</td>
                        <td>{{ $customer->address ?: '-' }}</td>
                        <td><strong>{{ number_format($customer->points) }}</strong> điểm</td>
                        <td>                            @if($customer->type === 'VIP')
                                <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill"><i class="fas fa-crown me-1"></i>VIP</span>
                            @elseif($customer->type === 'Thường')
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-user me-1"></i>Thường</span>
                            @else
                                <span class="badge bg-info-subtle text-info border border-info px-3 py-2 rounded-pill"><i class="fas fa-user-plus me-1"></i>Mới</span>
                            @endif</td>
                        <td>{{ $customer->created_at?->format('d/m/Y') }}</td>
                        <td><div class="d-flex gap-2">
                            <a href="{{ route('customers.show', $customer) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('customers.edit', $customer) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route('customers.destroy', $customer) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa?')">@csrf @method('DELETE')<button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button></form>
                        </div></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Chưa có khách hàng nào</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($customers->hasPages())
<!-- Pagination -->
<nav class="mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">
            Hiển thị {{ $customers->firstItem() }} - {{ $customers->lastItem() }} của {{ $customers->total() }} khách hàng
        </div>
        <ul class="pagination mb-0">
            @if ($customers->onFirstPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $customers->appends(request()->query())->url($customers->currentPage() - 1) }}"><i class="bi bi-chevron-left"></i></a></li>
            @endif
            @foreach ($customers->getUrlRange(max(1, $customers->currentPage() - 2), min($customers->lastPage(), $customers->currentPage() + 2)) as $page => $url)
                @if ($page == $customers->currentPage())
                    <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $customers->appends(request()->query())->url($page) }}">{{ $page }}</a></li>
                @endif
            @endforeach
            @if ($customers->onLastPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $customers->appends(request()->query())->url($customers->currentPage() + 1) }}"><i class="bi bi-chevron-right"></i></a></li>
            @endif
        </ul>
    </div>
</nav>
@endif
@endsection
