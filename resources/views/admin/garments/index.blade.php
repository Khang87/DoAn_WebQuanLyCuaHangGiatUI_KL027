@extends('layouts.app')

@section('title', 'Quản Lý Loại Đồ Giặt - Sky Laundry')
@section('page-title', 'Quản Lý Loại Đồ Giặt & Tình Trạng Sản Phẩm')

@section('content')
<!-- Page Actions -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="{{ route('garments.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-2"></i>Thêm Loại Đồ Giặt
        </a>
    </div>
</div>
<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-5">
        <div class="input-group shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control border-start-0 py-2 ps-2" placeholder="Tìm kiếm loại đồ, hiện trạng..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-md-3">
        <select name="category" class="form-select shadow-sm rounded-3 py-2" style="min-width: 220px;" onchange="this.form.submit()">
            <option value="">-- Tất cả danh mục --</option>
            @foreach($categories as $cat)
                <option value="{{ $cat }}" @selected(request('category') === $cat)>{{ $cat }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-md-3">
        <select name="status" class="form-select shadow-sm rounded-3 py-2" style="min-width: 200px;" onchange="this.form.submit()">
            <option value="">-- Tất cả trạng thái --</option>
            <option value="active" @selected(request('status') === 'active')>Đang hoạt động</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Tạm ngưng</option>
        </select>
    </div>
</form>

@php
    function getGarmentIcon($garment) {
        $name = mb_strtolower($garment->name ?? '');
        $cat = mb_strtolower($garment->category ?? '');

        if (str_contains($name, 'dài') || str_contains($name, 'váy') || str_contains($name, 'đầm') || str_contains($cat, 'truyền thống')) {
            return 'fa-solid fa-person-dress';
        }
        if (str_contains($name, 'khoác') || str_contains($name, 'blazer') || str_contains($name, 'suit') || str_contains($name, 'vest')) {
            return 'fa-solid fa-user-tie';
        }
        if (str_contains($name, 'quần') || str_contains($cat, 'công sở')) {
            return 'fa-solid fa-scissors';
        }
        if (str_contains($name, 'chăn') || str_contains($name, 'mền') || str_contains($name, 'ga') || str_contains($name, 'gối')) {
            return 'fa-solid fa-bed';
        }
        if (str_contains($name, 'giày') || str_contains($name, 'dép') || str_contains($name, 'vớ') || str_contains($name, 'tất')) {
            return 'fa-solid fa-shoe-prints';
        }

        return 'fa-solid fa-shirt';
    }
@endphp

<!-- Garments Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        @php
                            $currentSortBy = request('sort_by');
                            $currentSortOrder = request('sort_order', 'desc');
                            $nextOrderName = ($currentSortBy === 'name' && $currentSortOrder === 'asc') ? 'desc' : 'asc';
                            $nextOrderPrice = ($currentSortBy === 'price' && $currentSortOrder === 'asc') ? 'desc' : 'asc';
                        @endphp
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'name', 'sort_order' => $nextOrderName]) }}" class="text-dark text-decoration-none">
                                Tên Loại Đồ
                                @if($currentSortBy === 'name') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>Danh Mục</th>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'price', 'sort_order' => $nextOrderPrice]) }}" class="text-dark text-decoration-none">
                                Giá Dịch Vụ
                                @if($currentSortBy === 'price') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>Mô Tả Hiện Trạng</th>
                        <th>Trạng Thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($garments as $garment)
                    @php $garmentIcon = getGarmentIcon($garment); @endphp
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-primary-subtle text-primary rounded-circle p-2 me-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                    <i class="{{ $garmentIcon }}"></i>
                                </div>
                                <strong class="fw-semibold">{{ $garment->name }}</strong>
                            </div>
                        </td>
                        <td><span class="badge bg-light text-dark border">{{ $garment->category ?: 'Đồ giặt' }}</span></td>
                        <td><strong class="text-primary">{{ number_format($garment->price) }} VNĐ</strong></td>
                        <td><small class="text-muted">{{ $garment->condition_note ?: 'Chưa ghi nhận hiện trạng' }}</small></td>
                        <td>
                            @if($garment->status === 'active')
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Hoạt động</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-ban me-1"></i>Khóa</span>
                            @endif
                        </td>
                         <td>
                             <div class="d-flex gap-2">
                                 <a href="{{ route('garments.show', $garment->id) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                 <a href="{{ route('garments.edit', $garment->id) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                 <form action="{{ route('garments.destroy', $garment->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa loại đồ giặt này?')">
                                     @csrf @method('DELETE')
                                     <button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button>
                                 </form>
                             </div>
                         </td>
                     </tr>
                     @empty
                     <tr>
                         <td colspan="6" class="text-center text-muted py-4">Chưa có thông tin loại đồ giặt nào.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($garments->hasPages())
<nav class="mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">Hiển thị {{ $garments->firstItem() }} - {{ $garments->lastItem() }} của {{ $garments->total() }} loại đồ</div>
        <ul class="pagination mb-0">
            @if ($garments->onFirstPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $garments->appends(request()->query())->url($garments->currentPage() - 1) }}"><i class="bi bi-chevron-left"></i></a></li>
            @endif
            @foreach ($garments->getUrlRange(max(1, $garments->currentPage() - 2), min($garments->lastPage(), $garments->currentPage() + 2)) as $page => $url)
                @if ($page == $garments->currentPage())
                    <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $garments->appends(request()->query())->url($page) }}">{{ $page }}</a></li>
                @endif
            @endforeach
            @if ($garments->onLastPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $garments->appends(request()->query())->url($garments->currentPage() + 1) }}"><i class="bi bi-chevron-right"></i></a></li>
            @endif
        </ul>
    </div>
</nav>
@endif
@endsection
