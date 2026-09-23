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
                            <i class="{{ $iconConfig['icon'] }} fs-4"></i>
                        </div>
                        <h5 class="mb-0">{{ $service->name }}</h5>
                    </div>
                    <span class="badge {{ $service->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ $service->status === 'active' ? 'Đang hoạt động' : 'Tạm ngưng' }}</span>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="small text-muted">Loại dịch vụ</div>
                        <div class="fw-semibold">{{ $service->type ?: 'Giặt ủi' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Giá cơ bản</div>
                        <div class="fw-semibold text-primary">{{ number_format($service->price) }} VNĐ/{{ $service->unit ?: 'kg' }}</div>
                    </div>
                    <div class="col-12">
                        <div class="small text-muted">Mô tả</div>
                        <div>{{ $service->description ?: 'Chưa có mô tả chi tiết.' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h6 class="mb-3">Thao tác</h6>
                <div class="d-grid gap-2">
                    <a href="{{ route('services.edit', $service) }}" class="btn btn-warning">Chỉnh sửa</a>
                    <form action="{{ route('services.destroy', $service) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa dịch vụ?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger w-100">Xóa dịch vụ</button>
                    </form>
                    <a href="{{ route('services.index') }}" class="btn btn-outline-secondary">Quay lại danh sách</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
