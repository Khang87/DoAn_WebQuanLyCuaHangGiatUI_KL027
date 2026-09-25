@extends('layouts.app')

@section('title', 'Chi tiết danh mục - Sky Laundry')
@section('page-title', 'Chi tiết danh mục')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('service-categories.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Quay lại
    </a>
    <a href="{{ route('service-categories.edit', $category->id) }}" class="btn btn-primary btn-sm">
        <i class="bi bi-pencil me-1"></i>Chỉnh sửa
    </a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h5 class="mb-3">{{ $category->name }}</h5>
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr><td><strong>Slug</strong></td><td>{{ $category->slug }}</td></tr>
                    <tr><td><strong>Icon</strong></td><td>{{ $category->icon ?: '-' }}</td></tr>
                    <tr><td><strong>Trạng thái</strong></td>
                        <td>
                            @if($category->status === 'active')
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Hoạt động</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-ban me-1"></i>Khóa</span>
                            @endif
                        </td>
                    </tr>
                    <tr><td><strong>Ngày tạo</strong></td><td>{{ $category->created_at?->format('d/m/Y') }}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr><td><strong>Số dịch vụ</strong></td><td>{{ $category->services->count() }}</td></tr>
                </table>
            </div>
        </div>
        @if($category->description)
        <div class="mt-3"><strong>Mô tả:</strong><br>{{ $category->description }}</div>
        @endif
        @if($category->deleted_at)
        <div class="mt-3">
            <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-ban me-1"></i>Đã xóa</span>
            <a href="{{ route('service-categories.restore', $category->id) }}" class="btn btn-sm btn-outline-success" onclick="return confirm('Khôi phục danh mục này?')">Khôi phục</a>
        </div>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Dịch vụ trong danh mục</h5></div>
    <div class="card-body p-0">
        @if($services->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Tên</th><th>Loại</th><th>Đơn giá</th><th>Đơn vị</th><th>Trạng thái</th></tr></thead>
                <tbody>
                    @foreach($services as $service)
                    <tr>
                        <td>{{ $service->name }}</td>
                        <td>{{ $service->type ?: '-' }}</td>
                        <td>{{ number_format($service->price) }} VNĐ</td>
                        <td>{{ $service->unit }}</td>
                        <td>
                            @if($service->status === 'active')
                                <span class="badge bg-success-subtle text-success border border-success px-2 py-1 rounded-pill"><i class="fas fa-check-circle"></i></span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1 rounded-pill"><i class="fas fa-ban"></i></span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $services->links() }}
        @else
        <p class="text-center text-muted py-4">Chưa có dịch vụ</p>
        @endif
    </div>
</div>
@endsection
