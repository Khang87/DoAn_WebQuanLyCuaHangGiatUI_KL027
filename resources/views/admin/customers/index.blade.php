@extends('layouts.app')

@section('title', 'Quản Lý Khách Hàng - Giặt Ủi Pro')
@section('page-title', 'Quản Lý Khách Hàng')

@section('content')
<!-- Page Actions -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="{{ route('customers.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus me-2"></i>Thêm Khách Hàng
        </a>
    </div>
    <div class="d-flex gap-2">
        <form method="GET" action="{{ route('customers.index') }}" class="d-flex gap-2">
            <div class="input-group" style="width: 300px;">
                <input type="text" name="search" class="form-control" placeholder="Tìm kiếm khách hàng..." value="{{ request('search') }}">
                <button class="btn btn-outline-secondary" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </div>
            <select name="type" class="form-select" style="width: auto;">
                <option value="">Tất cả loại</option>
                <option value="VIP" {{ request('type') === 'VIP' ? 'selected' : '' }}>VIP</option>
                <option value="Thường" {{ request('type') === 'Thường' ? 'selected' : '' }}>Thường</option>
                <option value="Mới" {{ request('type') === 'Mới' ? 'selected' : '' }}>Mới</option>
            </select>
            <button type="submit" class="btn btn-outline-secondary">Lọc</button>
            <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">Xóa</a>
        </form>
    </div>
</div>

<!-- Customers Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th>Mã khách hàng</th>
                        <th>Khách Hàng</th>
                        <th>Email</th>
                        <th>Số Điện Thoại</th>
                        <th>Địa Chỉ</th>
                        <th>Điểm Tích Lũy</th>
                        <th>Loại</th>
                        <th>Ngày Đăng Ký</th>
                        <th>Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                    <tr>
                        <td><strong>{{ $customer->code }}</strong></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="{{ asset('assets/images/user.jfif') }}" alt="Ảnh khách hàng" class="rounded-circle me-2" style="width: 40px; height: 40px; object-fit: cover;" onerror="this.onerror=null; this.src='{{ asset('assets/images/user.jfif') }}';">
                                <div class="fw-semibold">{{ $customer->name }}</div>
                            </div>
                        </td>
                        <td>{{ $customer->email ?: '-' }}</td>
                        <td>{{ $customer->phone ?: '-' }}</td>
                        <td>{{ $customer->address ?: '-' }}</td>
                        <td><strong>{{ number_format($customer->points) }}</strong> điểm</td>
                        <td><span class="customer-type-badge {{ $customer->type === 'VIP' ? 'vip' : ($customer->type === 'Mới' ? 'new' : 'regular') }}">{{ $customer->type }}</span></td>
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
