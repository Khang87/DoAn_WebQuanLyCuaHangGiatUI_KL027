@extends('layouts.app')

@section('title', 'Quản lý Danh mục loại đồ giặt - Sky Laundry')
@section('page-title', 'Quản lý Danh mục loại đồ giặt')

@section('content')
<!-- Page Actions: nút "Thêm" luôn nằm góc trên bên trái -->
<div class="page-toolbar">
    <a href="{{ route('garment-categories.create') }}" class="btn btn-create">
        <i class="bi bi-plus-lg"></i>Thêm danh mục
    </a>
    <p class="text-muted page-toolbar__desc">Nhóm các loại đồ giặt để phân loại và tra cứu nhanh trong đơn hàng.</p>
</div>
<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-auto flex-grow-1">
        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control form-control-sm border-start-0 ps-2" placeholder="Tìm kiếm danh mục..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <x-admin.status-select
            name="status"
            id="filter-status"
            :options="$statuses ?? \App\Enums\RecordStatus::options()"
            placeholder="-- Tất cả trạng thái --"
            class="form-select form-select-sm filter-select shadow-sm rounded-3"
            submit
        />
    </div>
</form>

<!-- Categories Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th class="fw-bold text-dark">STT</th>
                        <th class="fw-bold text-dark">Tên danh mục</th>
                        <th class="fw-bold text-dark">Slug</th>
                        <th class="fw-bold text-dark">Icon</th>
                        <th class="fw-bold text-dark">Thứ tự</th>
                        <th class="fw-bold text-dark">Trạng thái</th>
                        <th class="fw-bold text-dark">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                    <tr>
                        <td class="text-dark">{{ $loop->iteration }}</td>
                        <td class="text-dark">{{ $category->name }}</td>
                        <td class="text-dark">{{ $category->slug }}</td>
                        <td>
                            @if($category->icon)
                                <i class="{{ $category->icon }} fa-2x text-primary"></i>
                            @else
                                <span class="text-muted">Chưa có icon</span>
                            @endif
                        </td>
                        <td class="text-dark">{{ $category->sort_order }}</td>
                        <td>
                            <x-admin.status-badge :status="$category->status" :enum="\App\Enums\RecordStatus::class" />
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('garment-categories.show', $category) }}" class="btn btn-outline-secondary btn-sm" title="Xem"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('garment-categories.edit', $category) }}" class="btn btn-outline-secondary btn-sm" title="Sửa"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('garment-categories.destroy', $category) }}" method="POST" class="d-inline" id="deleteCategoryForm_{{ $category->id }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-outline-secondary btn-sm" title="Xóa"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Chưa có danh mục loại đồ giặt nào</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($categories->hasPages())
<!-- Pagination -->
<nav class="mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">
            Hiển thị {{ $categories->firstItem() }} - {{ $categories->lastItem() }} của {{ $categories->total() }} danh mục
        </div>
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

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[id^="deleteCategoryForm_"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (typeof Swal === 'undefined') {
                    if (confirm('Bạn có chắc muốn xóa danh mục này?')) {
                        form.submit();
                    }
                    return;
                }

                Swal.fire({
                    title: 'Xóa danh mục?',
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