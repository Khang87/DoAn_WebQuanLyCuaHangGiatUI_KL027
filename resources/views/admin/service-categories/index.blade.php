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

<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">ID</th>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">Tên danh mục</th>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">Icon</th>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">Mô tả</th>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">Trạng thái</th>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                    <tr>
                        <td class="text-start align-middle py-3 fw-bold text-dark">{{ $category->id }}</td>
                        <td class="text-start align-middle py-3 fw-bold text-muted">{{ $category->name ?: '-' }}</td>
                        <td class="text-start align-middle py-3 text-muted">{{ $category->icon ?: '-' }}</td>
                        <td class="text-start align-middle py-3 text-muted">{{ \Illuminate\Support\Str::limit($category->description, 50) ?: '-' }}</td>
                        <td class="text-start align-middle py-3">
                            @if($category->status === 'active')
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2"><i class="fas fa-check-circle me-1"></i>Hoạt động</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-2"><i class="fas fa-times-circle me-1"></i>Đã khóa</span>
                            @endif
                        </td>
                        <td class="text-start align-middle py-3">
                            <div class="d-flex align-items-center gap-1">
                                <a href="{{ route('service-categories.show', $category) }}" class="btn btn-sm btn-outline-info" title="Xem chi tiết"><i class="fas fa-eye"></i></a>
                                <a href="{{ route('service-categories.edit', $category) }}" class="btn btn-sm btn-outline-warning" title="Chỉnh sửa"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('service-categories.toggle-status', $category) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-{{ $category->status === 'active' ? 'warning' : 'success' }}" title="{{ $category->status === 'active' ? 'Khóa' : 'Kích hoạt' }}">
                                        <i class="fas {{ $category->status === 'active' ? 'fa-pause' : 'fa-play' }}"></i>
                                    </button>
                                </form>
                                <form action="{{ route('service-categories.destroy', $category) }}" method="POST" class="d-inline" onsubmit="return confirm('Xóa danh mục này?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa"><i class="fas fa-trash-alt"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="fas fa-search fa-2x mb-2 text-secondary d-block"></i>
                            Không tìm thấy dữ liệu phù hợp
                        </td>
                    </tr>
                    @endforelse
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
