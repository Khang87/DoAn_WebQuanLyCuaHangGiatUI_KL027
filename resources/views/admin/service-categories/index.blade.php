@extends('layouts.app')

@section('title', 'Danh Mục Dịch Vụ - Sky Laundry')
@section('page-title', 'Danh Mục Dịch Vụ')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="{{ route('service-categories.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i>Thêm danh mục
        </a>
    </div>
</div>
<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-5">
        <div class="input-group shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control border-start-0 py-2 ps-2" placeholder="Tìm theo ID, mã danh mục, tên dịch vụ..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-md-4">
        <select name="status" class="form-select shadow-sm rounded-3 py-2" style="min-width: 220px;" onchange="this.form.submit()">
            <option value="">-- Tất cả trạng thái --</option>
            <option value="active" @selected(request('status') === 'active')>Hoạt động</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Tắt</option>
        </select>
    </div>
</form>

<!-- Categories Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        @php
                            $currentSortBy = request('sort_by');
                            $currentSortOrder = request('sort_order', 'desc');
                            $nextOrderId = ($currentSortBy === 'id' && $currentSortOrder === 'asc') ? 'desc' : 'asc';
                            $nextOrderName = ($currentSortBy === 'name' && $currentSortOrder === 'asc') ? 'desc' : 'asc';
                        @endphp
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'id', 'sort_order' => $nextOrderId]) }}" class="text-dark text-decoration-none">
                                ID
                                @if($currentSortBy === 'id') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => $nextOrderName]) }}" class="text-dark text-decoration-none">
                                Tên danh mục
                                @if($currentSortBy === 'name') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>Icon</th>
                        <th>Mô tả</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                    <tr>
                        <td><strong>{{ $category->id }}</strong></td>
                        <td><strong>{{ $category->name ?: '-' }}</strong></td>
                        <td>{{ $category->icon ?: '-' }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($category->description, 50) ?: '-' }}</td>
                        <td>
                            @if($category->status === 'active')
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Hoạt động</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-times-circle me-1"></i>Đã khóa</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="d-flex gap-2">
                                <a href="{{ route('service-categories.show', $category) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('service-categories.edit', $category) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('service-categories.toggle-status', $category) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-order-action" title="{{ $category->status === 'active' ? 'Khóa' : 'Kích hoạt' }}">
                                        <i class="fas {{ $category->status === 'active' ? 'fa-pause' : 'fa-play' }}"></i>
                                    </button>
                                </form>
                                <form action="{{ route('service-categories.destroy', $category) }}" method="POST" class="d-inline" onsubmit="return confirm('Xóa danh mục này?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Chưa có dữ liệu nào</td>
                    </tr>
                    @endempty
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($categories->hasPages())
<nav class="mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">Hiển thị {{ $categories->firstItem() }} - {{ $categories->lastItem() }} của {{ $categories->total() }} danh mục</div>
        <ul class="pagination mb-0">
            @if ($categories->onFirstPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $categories->appends(request()->query())->url($categories->currentPage() - 1) }}"><i class="bi bi-chevron-left"></i></a></li>
            @endif
            @foreach ($categories->getUrlRange(max(1, $categories->currentPage() - 2), min($categories->lastPage(), $categories->currentPage() + 2)) as $page => $url)
                @if ($page == $categories->currentPage())
                    <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $categories->appends(request()->query())->url($page) }}">{{ $page }}</a></li>
                @endif
            @endforeach
            @if ($categories->onLastPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $categories->appends(request()->query())->url($categories->currentPage() + 1) }}"><i class="bi bi-chevron-right"></i></a></li>
            @endif
        </ul>
    </div>
</nav>
@endif
@endsection
