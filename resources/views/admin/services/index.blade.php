@extends('layouts.app')

@section('title', 'Quản lý dịch vụ - Sky Laundry')
@section('page-title', 'Quản lý dịch vụ')

@section('content')
<!-- Page Actions -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="{{ route('services.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i>Thêm dịch vụ
        </a>
    </div>
</div>
<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-5">
        <div class="input-group shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control border-start-0 py-2 ps-2" placeholder="Tìm kiếm dịch vụ..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-md-3">
        <select name="category_id" class="form-select shadow-sm rounded-3 py-2" style="min-width: 220px;" onchange="this.form.submit()">
            <option value="">-- Tất cả danh mục --</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-md-2">
        <select name="status" class="form-select shadow-sm rounded-3 py-2" style="min-width: 180px;" onchange="this.form.submit()">
            <option value="">-- Trạng thái --</option>
            @foreach($statuses ?? \App\Enums\RecordStatus::options() as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-md-2">
        <select name="sort" class="form-select shadow-sm rounded-3 py-2" style="min-width: 180px;" onchange="this.form.submit()">
            <option value="">-- Sắp xếp --</option>
            <option value="created_at_desc" @selected(request('sort') === 'created_at_desc')>Mới nhất</option>
            <option value="created_at_asc" @selected(request('sort') === 'created_at_asc')>Cũ nhất</option>
            <option value="price_asc" @selected(request('sort') === 'price_asc')>Giá tăng dần</option>
            <option value="price_desc" @selected(request('sort') === 'price_desc')>Giá giảm dần</option>
            <option value="name_asc" @selected(request('sort') === 'name_asc')>Tên A-Z</option>
            <option value="name_desc" @selected(request('sort') === 'name_desc')>Tên Z-A</option>
            <option value="status_asc" @selected(request('sort') === 'status_asc')>Trạng thái A-Z</option>
            <option value="status_desc" @selected(request('sort') === 'status_desc')>Trạng thái Z-A</option>
        </select>
    </div>
</form>


<!-- Services Cards -->
<div class="row g-4">
    @forelse($services as $service)
    @php $iconConfig = $service->iconConfig(); @endphp
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="{{ $iconConfig['bg'] }} text-white rounded p-3 me-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                        <i class="{{ $iconConfig['icon'] }} fs-4"></i>
                    </div>
                    <div>
                         <h5 class="card-title mb-1">{{ $service->name }}</h5>
                        <div class="d-flex gap-1 flex-wrap mt-1">
                            @if($service->category)
                                <span class="badge bg-secondary-subtle text-secondary border px-2 py-1 rounded-pill text-xs">{{ $service->category->name }}</span>
                            @endif
                            @if($service->status === 'active')
                                <span class="badge bg-success-subtle text-success border border-success px-2 py-1 rounded-pill text-xs"><i class="fas fa-check-circle me-1"></i>{{ $service->status_label ?? 'Đang hoạt động' }}</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary px-2 py-1 rounded-pill text-xs"><i class="fas fa-pause-circle me-1"></i>{{ $service->status_label ?? 'Tạm ngưng' }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <p class="text-muted mb-3">{{ $service->description ?: 'Chưa có mô tả chi tiết.' }}</p>
                <div class="mb-3">
                    <strong>Giá cơ bản:</strong> <span class="text-primary">{{ number_format($service->price) }} VNĐ/{{ $service->unit ?: 'kg' }}</span>
                </div>
                @if($service->processing_time)
                <div class="mb-2">
                    <span class="badge bg-light text-dark border"><i class="far fa-clock text-warning me-1"></i>{{ $service->formatted_processing_time }}</span>
                </div>
                @endif
                <div class="d-flex gap-2">
                    <a href="{{ route('services.show', $service) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                    <a href="{{ route('services.edit', $service) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="alert alert-info text-center">Chưa có dịch vụ nào trong hệ thống.</div>
    </div>
    @endforelse
</div>

@if($services->hasPages())
<nav class="mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">Hiển thị {{ $services->firstItem() }} - {{ $services->lastItem() }} của {{ $services->total() }} dịch vụ</div>
        <ul class="pagination mb-0">
            @if ($services->onFirstPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $services->appends(request()->query())->url($services->currentPage() - 1) }}"><i class="bi bi-chevron-left"></i></a></li>
            @endif
            @foreach ($services->getUrlRange(max(1, $services->currentPage() - 2), min($services->lastPage(), $services->currentPage() + 2)) as $page => $url)
                @if ($page == $services->currentPage())
                    <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $services->appends(request()->query())->url($page) }}">{{ $page }}</a></li>
                @endif
            @endforeach
            @if ($services->onLastPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $services->appends(request()->query())->url($services->currentPage() + 1) }}"><i class="bi bi-chevron-right"></i></a></li>
            @endif
        </ul>
    </div>
</nav>
@endif
@endsection