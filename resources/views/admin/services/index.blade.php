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

<!-- Services Cards -->
<div class="row g-4">
    <!-- Service Card 1 -->
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-primary text-white rounded p-3 me-3">
                        <i class="bi bi-droplet fs-4"></i>
                    </div>
                    <div>
                        <h5 class="card-title mb-1">Giặt thường</h5>
                        <span class="badge bg-success">Đang hoạt động</span>
                    </div>
                </div>
                <p class="text-muted mb-3">Dịch vụ giặt ủi cơ bản cho quần áo hàng ngày. Thời gian xử lý 2-3 ngày.</p>
                <div class="mb-3">
                    <strong>Giá cơ bản:</strong> <span class="text-primary">25,000 VNĐ/kg</span>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('services.edit', 1) }}" class="btn btn-sm btn-outline-warning flex-grow-1">
                        <i class="bi bi-pencil me-1"></i>Sửa
                    </a>
                    <form action="{{ route('services.destroy', 1) }}" method="POST" class="flex-grow-1" onsubmit="return confirm('Bạn có chắc muốn xóa?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                            <i class="bi bi-trash me-1"></i>Xóa
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Service Card 2 -->
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-info text-white rounded p-3 me-3">
                        <i class="bi bi-snow fs-4"></i>
                    </div>
                    <div>
                        <h5 class="card-title mb-1">Giặt Khô</h5>
                        <span class="badge bg-success">Đang hoạt động</span>
                    </div>
                </div>
                <p class="text-muted mb-3">Dịch vụ giặt khô chuyên dụng cho quần áo cao cấp, vải đặc biệt. Thời gian xử lý 3-4 ngày.</p>
                <div class="mb-3">
                    <strong>Giá cơ bản:</strong> <span class="text-primary">45,000 VNĐ/kg</span>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('services.edit', 2) }}" class="btn btn-sm btn-outline-warning flex-grow-1">
                        <i class="bi bi-pencil me-1"></i>Sửa
                    </a>
                    <form action="{{ route('services.destroy', 2) }}" method="POST" class="flex-grow-1" onsubmit="return confirm('Bạn có chắc muốn xóa?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                            <i class="bi bi-trash me-1"></i>Xóa
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Service Card 3 -->
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-warning text-white rounded p-3 me-3">
                        <i class="bi bi-wind fs-4"></i>
                    </div>
                    <div>
                        <h5 class="card-title mb-1">Ủi đồ</h5>
                        <span class="badge bg-success">Đang hoạt động</span>
                    </div>
                </div>
                <p class="text-muted mb-3">Dịch vụ ủi chuyên nghiệp với hơi nước nóng, giúp quần áo phẳng và thơm lâu.</p>
                <div class="mb-3">
                    <strong>Giá cơ bản:</strong> <span class="text-primary">15,000 VNĐ/món</span>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('services.edit', 3) }}" class="btn btn-sm btn-outline-warning flex-grow-1">
                        <i class="bi bi-pencil me-1"></i>Sửa
                    </a>
                    <form action="{{ route('services.destroy', 3) }}" method="POST" class="flex-grow-1" onsubmit="return confirm('Bạn có chắc muốn xóa?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                            <i class="bi bi-trash me-1"></i>Xóa
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Service Card 4 -->
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-danger text-white rounded p-3 me-3">
                        <i class="bi bi-tornado fs-4"></i>
                    </div>
                    <div>
                        <h5 class="card-title mb-1">Giặt chăn mền</h5>
                        <span class="badge bg-success">Đang hoạt động</span>
                    </div>
                </div>
                <p class="text-muted mb-3">Dịch vụ giặt thảm, chăn ga, gối đệm chuyên nghiệp. Thời gian xử lý 5-7 ngày.</p>
                <div class="mb-3">
                    <strong>Giá cơ bản:</strong> <span class="text-primary">80,000 VNĐ/món</span>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('services.edit', 4) }}" class="btn btn-sm btn-outline-warning flex-grow-1">
                        <i class="bi bi-pencil me-1"></i>Sửa
                    </a>
                    <form action="{{ route('services.destroy', 4) }}" method="POST" class="flex-grow-1" onsubmit="return confirm('Bạn có chắc muốn xóa?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                            <i class="bi bi-trash me-1"></i>Xóa
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Service Card 5 -->
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-secondary text-white rounded p-3 me-3">
                        <i class="bi bi-lightning fs-4"></i>
                    </div>
                    <div>
                        <h5 class="card-title mb-1">Giặt giày</h5>
                        <span class="badge bg-success">Đang hoạt động</span>
                    </div>
                </div>
                <p class="text-muted mb-3">Dịch vụ giặt nhanh trong ngày. Phù hợp khi cần gấp. Hoàn thành trong 6-8 giờ.</p>
                <div class="mb-3">
                    <strong>Giá cơ bản:</strong> <span class="text-primary">50,000 VNĐ/kg</span>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('services.edit', 5) }}" class="btn btn-sm btn-outline-warning flex-grow-1">
                        <i class="bi bi-pencil me-1"></i>Sửa
                    </a>
                    <form action="{{ route('services.destroy', 5) }}" method="POST" class="flex-grow-1" onsubmit="return confirm('Bạn có chắc muốn xóa?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                            <i class="bi bi-trash me-1"></i>Xóa
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
