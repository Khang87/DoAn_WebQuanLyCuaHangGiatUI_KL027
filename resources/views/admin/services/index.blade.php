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
    <div class="input-group" style="width: 300px;">
        <input type="text" class="form-control" placeholder="Tìm kiếm dịch vụ...">
        <button class="btn btn-outline-secondary" type="button">
            <i class="bi bi-search"></i>
        </button>
    </div>
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

    $sampleServices = [
        (object)['id' => 1, 'name' => 'Giặt thường', 'type' => 'Đồ thường', 'price' => 25000, 'unit' => 'kg', 'status' => 'active', 'description' => 'Dịch vụ giặt ủi cơ bản cho quần áo hàng ngày. Thời gian xử lý 2-3 ngày.'],
        (object)['id' => 2, 'name' => 'Giặt Khô', 'type' => 'Quần áo cao cấp', 'price' => 45000, 'unit' => 'kg', 'status' => 'active', 'description' => 'Dịch vụ giặt khô chuyên dụng cho quần áo cao cấp, vải đặc biệt. Thời gian xử lý 3-4 ngày.'],
        (object)['id' => 3, 'name' => 'Ủi đồ', 'type' => 'Ủi hơi nước', 'price' => 15000, 'unit' => 'món', 'status' => 'active', 'description' => 'Dịch vụ ủi chuyên nghiệp với hơi nước nóng, giúp quần áo phẳng và thơm lâu.'],
        (object)['id' => 4, 'name' => 'Giặt chăn mền', 'type' => 'Chăn ga gối', 'price' => 80000, 'unit' => 'món', 'status' => 'active', 'description' => 'Dịch vụ giặt thảm, chăn ga, gối đệm chuyên nghiệp. Thời gian xử lý 5-7 ngày.'],
        (object)['id' => 5, 'name' => 'Giặt giày', 'type' => 'Giày dép', 'price' => 50000, 'unit' => 'đôi', 'status' => 'active', 'description' => 'Dịch vụ giặt nhanh trong ngày. Phù hợp khi cần gấp. Hoàn thành trong 6-8 giờ.'],
    ];
    $displayServices = $services->count() > 0 ? $services : collect($sampleServices);
@endphp

<!-- Services Cards -->
<div class="row g-4">
    @forelse($displayServices as $service)
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
                        <span class="badge {{ $service->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ $service->status === 'active' ? 'Đang hoạt động' : 'Tạm ngưng' }}</span>
                    </div>
                </div>
                <p class="text-muted mb-3">{{ $service->description ?: 'Chưa có mô tả chi tiết.' }}</p>
                <div class="mb-3">
                    <strong>Giá cơ bản:</strong> <span class="text-primary">{{ number_format($service->price) }} VNĐ/{{ $service->unit ?: 'kg' }}</span>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('services.edit', $service) }}" class="btn btn-sm btn-outline-warning flex-grow-1">
                        <i class="bi bi-pencil me-1"></i>Sửa
                    </a>
                    <form action="{{ route('services.destroy', $service) }}" method="POST" class="flex-grow-1" onsubmit="return confirm('Bạn có chắc muốn xóa?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                            <i class="bi bi-trash me-1"></i>Xóa
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
@endsection
