@extends('layouts.app')

@section('title', 'Quản lý dịch vụ - Sky Laundry')
@section('page-title', 'Quản lý dịch vụ')

@section('content')
<!-- Page Actions: nút "Thêm" luôn nằm góc trên bên trái -->
<div class="page-toolbar">
    <a href="{{ route('services.create') }}" class="btn btn-create">
        <i class="bi bi-plus-lg"></i>Thêm dịch vụ
    </a>
    <p class="text-muted page-toolbar__desc">Danh mục các dịch vụ giặt ủi đang cung cấp kèm đơn giá và thời gian xử lý.</p>
</div>
<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-auto flex-grow-1">
        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control form-control-sm border-start-0 ps-2" placeholder="Tìm kiếm dịch vụ..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select id="category-filter" name="category_id" class="form-select form-select-sm filter-select shadow-sm rounded-3 js-icon-select" onchange="this.form.submit()">
            <option value="">-- Tất cả danh mục --</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" data-icon="{{ $category->icon }}" @selected(request('category_id') == $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
        <i class="icon-preview" hidden></i>
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
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="sort" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="">-- Tất cả cách sắp xếp --</option>
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
    <div class="col-12 col-xl-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center text-white me-3" style="width: 48px; height: 48px; background-color: #00c4cc; flex-shrink: 0;">
                        @if(!empty($service->icon))
                            <i class="{{ $service->icon }} fs-4"></i>
                        @else
                            <i class="bi bi-water fs-4"></i>
                        @endif
                    </div>
                    <div>
                         <h5 class="card-title mb-1">{{ $service->name }}</h5>
                        <div class="d-flex gap-1 flex-wrap mt-1">
                            @if($service->category)
                                <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary px-2 py-1 rounded-pill text-xs">{{ $service->category->name }}</span>
                            @endif
                            <x-admin.status-badge :status="$service->status" :enum="\App\Enums\RecordStatus::class" size="px-2 py-1" class="text-xs" />
                        </div>
                    </div>
                </div>
                <p class="text-muted mb-3">{{ $service->description ?: 'Chưa có mô tả chi tiết.' }}</p>
                    <div class="mb-3">
                        <strong>Giá cơ bản:</strong> <span class="text-dark">{{ number_format($service->price) }} VNĐ/{{ $service->unit ?: 'kg' }}</span>
                    </div>
                @if($service->processing_time)
                <div class="mb-2">
                    <span class="badge rounded-pill px-2.5 py-1 fw-medium" style="background-color: #fef3c7 !important; color: #b45309 !important; border: 1px solid #fde68a !important; font-size: 0.8125rem; display: inline-flex; align-items: center; width: fit-content;">
                        <i class="bi bi-clock me-1" style="color: #b45309;"></i> {{ $service->formatted_processing_time }}
                    </span>
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