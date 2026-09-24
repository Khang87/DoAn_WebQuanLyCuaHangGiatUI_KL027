@extends('layouts.app')

@section('title', 'Quản Lý Dịch Vụ - Giặt Ủi Pro')
@section('page-title', 'Quản Lý Dịch Vụ')

@section('content')
<!-- Page Actions -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="{{ route('services.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i>Thêm Dịch Vụ
        </a>
    </div>
    <form action="{{ route('services.index') }}" method="GET" class="d-flex gap-2">
        <div class="input-group" style="width: 300px;">
            <input type="text" name="search" class="form-control" placeholder="Tìm kiếm dịch vụ..." value="{{ request('search') }}">
            <button class="btn btn-outline-secondary" type="submit">
                <i class="bi bi-search"></i>
            </button>
        </div>
        <select name="category_id" class="form-select" style="width: auto;" onchange="this.form.submit()">
            <option value="">Tất cả danh mục</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
        <select name="status" class="form-select" style="width: auto;" onchange="this.form.submit()">
            <option value="">Tất cả trạng thái</option>
            <option value="active" @selected(request('status') === 'active')>Đang hoạt động</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Tạm ngưng</option>
        </select>
    </form>
</div>

@php
    function getServiceIconConfig($service) {
        $name = mb_strtolower($service->name ?? '');
        $type = mb_strtolower($service->type ?? '');

        if (str_contains($name, 'khô') || str_contains($type, 'khô') || str_contains($name, 'hấp') || str_contains($type, 'dry')) {
            return ['icon' => 'fa-solid fa-shirt', 'bg' => 'bg-info'];
        }
        if (str_contains($name, 'ủi') || str_contains($type, 'ủi') || str_contains($type, 'iron')) {
            return ['icon' => 'fa-solid fa-jug-detergent', 'bg' => 'bg-warning text-dark'];
        }
        if (str_contains($name, 'chăn') || str_contains($name, 'mền') || str_contains($name, 'ga') || str_contains($name, 'thảm') || str_contains($type, 'chăn')) {
            return ['icon' => 'fa-solid fa-bed', 'bg' => 'bg-danger'];
        }
        if (str_contains($name, 'giày') || str_contains($type, 'giày') || str_contains($name, 'dép')) {
            return ['icon' => 'fa-solid fa-shoe-prints', 'bg' => 'bg-dark'];
        }
        if (str_contains($name, 'nhanh') || str_contains($name, 'tốc')) {
            return ['icon' => 'fa-solid fa-bolt', 'bg' => 'bg-secondary'];
        }

        return ['icon' => 'fa-solid fa-droplet', 'bg' => 'bg-primary'];
    }
@endphp

<!-- Services Cards -->
<div class="row g-4">
    @forelse($services as $service)
    @php $iconConfig = getServiceIconConfig($service); @endphp
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="{{ $iconConfig['bg'] }} text-white rounded p-3 me-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                        <i class="{{ $iconConfig['icon'] }} fs-4"></i>
                    </div>
                    <div>
                         <h5 class="card-title mb-1">{{ $service->name }}</h5>
                        @if($service->status === 'active')
                            <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Hoạt động</span>
                        @else
                            <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-ban me-1"></i>Khóa</span>
                        @endif
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
                    <form action="{{ route('services.toggle-status', $service) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn {{ $service->status === 'active' ? 'khóa' : 'kích hoạt' }} dịch vụ này?')">
                        @csrf
                        <button type="submit" class="btn btn-order-action {{ $service->status === 'active' ? 'delete' : 'view' }}" title="{{ $service->status === 'active' ? 'Khóa' : 'Kích hoạt' }}">
                            <i class="bi bi-{{ $service->status === 'active' ? 'lock' : 'unlock' }}"></i>
                        </button>
                    </form>
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
                                <li class="page-item"><a class="page-link" href="{{ $services->appends(request()->query())->url($services->currentPage() + 1) }}"><i class="bi bi-chevron-right"></i></a></li></li>
            @endif
        </ul>
    </div>
</nav>
@endif
@endsection
