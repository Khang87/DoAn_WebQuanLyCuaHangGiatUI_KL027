@extends('layouts.app')

@section('title', 'Quản lý khách hàng - Sky Laundry')
@section('page-title', 'Quản lý khách hàng')

@section('content')
<!-- Page Actions -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="{{ route('customers.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus me-2"></i>Thêm khách hàng
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
</form>

<!-- Customers Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th>STT</th>
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
                                Mã
                                @if($currentSortBy === 'code') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => $nextOrderName]) }}" class="text-dark text-decoration-none">
                                Khách hàng
                                @if($currentSortBy === 'name') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>Email</th>
                        <th>Số điện thoại</th>
                        <th>Địa chỉ</th>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'points', 'sort_order' => $nextOrderPoints]) }}" class="text-dark text-decoration-none">
                                Điểm tích lũy
                                @if($currentSortBy === 'points') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => $nextOrderCreated]) }}" class="text-dark text-decoration-none">
                                Ngày đăng ký
                                @if($currentSortBy === 'created_at') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td><strong>{{ $customer->code }}</strong></td>
                        <td>
                            <div class="d-flex align-items-center">
                                @php
                                    $randomImage = 'assets/images/user_' . (($customer->id % 8) + 1) . '.jpg';
                                    $avatarUrl = (!empty($customer->avatar) && file_exists(public_path($customer->avatar))) 
                                               ? asset($customer->avatar) 
                                               : asset($randomImage);
                                @endphp
                                <img src="{{ $avatarUrl }}" alt="Avatar" class="rounded-circle me-2 avatar-cover" style="width: 40px; height: 40px;">
                                <div class="fw-semibold">{{ $customer->name }}</div>
                            </div>
                        </td>
                        <td>{{ $customer->email ?: '-' }}</td>
                        <td>{{ $customer->phone ?: '-' }}</td>
                        <td>{{ $customer->address ?: '-' }}</td>
                        <td><strong>{{ number_format($customer->points) }}</strong> điểm</td>
                        <td>{{ $customer->created_at?->format('d/m/Y') }}</td>
                        <td><div class="d-flex gap-2">
                            <a href="{{ route('customers.show', $customer) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('customers.edit', $customer) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route('customers.destroy', $customer) }}" method="POST" class="d-inline" id="deleteCustomerForm_{{ $customer->id }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button>
                            </form>
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

@if($customers->hasPages())
<nav class="mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">Hiển thị {{ $customers->firstItem() }} - {{ $customers->lastItem() }} của {{ $customers->total() }} khách hàng</div>
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
                <li class="page-item"><a class="page-link" href="{{ $customers->appends(request()->query())->url($customers->currentPage() + 1) }}">{{ $page }}</a></li>
            @endif
        </ul>
    </div>
</nav>
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[id^="deleteCustomerForm_"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (typeof Swal === 'undefined') {
                    if (confirm('Bạn có chắc muốn xóa khách hàng này?')) {
                        form.submit();
                    }
                    return;
                }

                Swal.fire({
                    title: 'Xóa khách hàng?',
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