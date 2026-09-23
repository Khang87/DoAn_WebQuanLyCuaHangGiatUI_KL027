@extends('layouts.app')

@section('title', 'Chi Tiết Loại Đồ Giặt - Giặt Ủi Pro')
@section('page-title', 'Chi Tiết Loại Đồ Giặt')

@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary-subtle text-primary rounded p-3 me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i class="fa-solid fa-shirt fs-4"></i>
                        </div>
                        <h5 class="mb-0">{{ $garment->name }}</h5>
                    </div>
                    <span class="badge {{ $garment->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ $garment->status === 'active' ? 'Đang hoạt động' : 'Tạm ngưng' }}</span>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="small text-muted">Danh mục loại đồ</div>
                        <div class="fw-semibold">{{ $garment->category ?: 'Đồ giặt' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small text-muted">Giá dịch vụ</div>
                        <div class="fw-semibold text-primary fs-5">{{ number_format($garment->price) }} VNĐ</div>
                    </div>
                    <div class="col-12">
                        <div class="small text-muted">Hiện trạng sản phẩm trước khi giặt</div>
                        <div class="p-3 bg-light rounded mt-1">{{ $garment->condition_note ?: 'Chưa có thông tin ghi nhận hiện trạng.' }}</div>
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
                    <a href="{{ route('garments.edit', $garment->id) }}" class="btn btn-warning">Chỉnh sửa</a>
                    <form action="{{ route('garments.destroy', $garment->id) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa loại đồ giặt này?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger w-100">Xóa loại đồ giặt</button>
                    </form>
                    <a href="{{ route('garments.index') }}" class="btn btn-outline-secondary">Quay lại danh sách</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
