@extends('layouts.app')

@section('title', 'Chi Tiết Dịch Vụ - Giặt Ủi Pro')
@section('page-title', 'Chi Tiết Dịch Vụ')

@section('content')
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

    $iconConfig = getServiceIconConfig($service);
@endphp

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="d-flex align-items-center">
                        <div class="{{ $iconConfig['bg'] }} text-white rounded p-3 me-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                            <i class="{{ $service->icon ?: $iconConfig['icon'] }} fs-4"></i>
                        </div>
                        <h5 class="mb-0">{{ $service->name }}</h5>
                    </div>
                    @if($service->status === 'active')
                        <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Hoạt động</span>
                    @else
                        <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-ban me-1"></i>Khóa</span>
                    @endif
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="small text-muted">Loại dịch vụ</div>
                        <div class="fw-semibold">{{ $service->type ?: 'Chưa phân loại' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Đơn giá</div>
                        <div class="fw-semibold text-primary">{{ number_format($service->price) }} VNĐ</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Đơn vị tính</div>
                        <div class="fw-semibold">{{ $service->unit ?: 'kg' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Thời gian xử lý</div>
                        <div class="fw-semibold">
                            @if($service->processing_time)
                                <span class="badge bg-light text-dark border"><i class="far fa-clock text-warning me-1"></i>{{ $service->formatted_processing_time }}</span>
                            @else
                                <span class="text-muted">Chưa cập nhật</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Thời gian xử lý</div>
                        <div class="fw-semibold">{{ $service->processing_time ? $service->processing_time . ' giờ' : 'Chưa cập nhật' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Danh mục</div>
                        <div class="fw-semibold">{{ $service->category?->name ?: 'Chưa phân loại' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Icon</div>
                        <div class="fw-semibold">{{ $service->icon ?: 'Chưa cài đặt' }}</div>
                    </div>
                    <div class="col-12">
                        <div class="small text-muted">Mô tả</div>
                        <div class="mt-1">{{ $service->description ?: 'Chưa có mô tả chi tiết.' }}</div>
                    </div>
                </div>

                @if($service->deleted_at)
                <div class="mt-4">
                    <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-ban me-1"></i>Đã ngưng sử dụng</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h6 class="mb-3">Thao tác</h6>
                <div class="d-grid gap-2">
                    <a href="{{ route('services.edit', $service) }}" class="btn btn-warning"><i class="bi bi-pencil me-1"></i>Chỉnh sửa</a>
                    @if($service->status === 'active')
                    <form action="{{ route('services.toggle-status', $service) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn khóa dịch vụ này?')">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger"><i class="bi bi-lock me-1"></i>Khóa dịch vụ</button>
                    </form>
                    @else
                    <form action="{{ route('services.toggle-status', $service) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn kích hoạt dịch vụ này?')">
                        @csrf
                        <button type="submit" class="btn btn-outline-success"><i class="bi bi-unlock me-1"></i>Kích hoạt</button>
                    </form>
                    @endif
                    <form action="{{ route('services.destroy', $service) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa dịch vụ này?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Xóa dịch vụ</button>
                    </form>
                    <a href="{{ route('services.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Quay lại danh sách</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
