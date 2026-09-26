@extends('layouts.app')

@section('title', 'Chỉnh sửa Danh mục loại đồ giặt - Sky Laundry')
@section('page-title', 'Chỉnh sửa Danh mục loại đồ giặt')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Chỉnh sửa danh mục: {{ $category->name }}</h5>
            <a href="{{ route('garment-categories.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('garment-categories.update', $category) }}" method="POST">
            @csrf @method('PUT')
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Tên danh mục <span class="text-danger ms-1">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $category->name) }}" placeholder="VD: Áo, Quần, Váy, Chăn ga gối..." required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Slug (URL) <span class="text-danger ms-1">*</span></label>
                    <input type="text" class="form-control @error('slug') is-invalid @enderror" name="slug" value="{{ old('slug', $category->slug) }}" placeholder="VD: ao-quan-vay-chan-ga-goi" required>
                    <div class="form-text">Tự động tạo từ tên nếu để trống. Chỉ chứa chữ thường, số và dấu gạch ngang.</div>
                    @error('slug')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Icon (FontAwesome class)</label>
                    <input type="text" class="form-control @error('icon') is-invalid @enderror" name="icon" value="{{ old('icon', $category->icon) }}" placeholder="VD: fa-solid fa-shirt">
                    <div class="form-text">Nhập class FontAwesome 6 (VD: fa-solid fa-shirt, fa-solid fa-person-dress...)</div>
                    @if($category->icon)
                        <div class="form-text text-primary mt-1">Icon hiện tại: <i class="{{ $category->icon }}"></i></div>
                    @endif
                    @error('icon')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Thứ tự sắp xếp</label>
                    <input type="number" class="form-control @error('sort_order') is-invalid @enderror" name="sort_order" value="{{ old('sort_order', $category->sort_order) }}" min="0" placeholder="0">
                    @error('sort_order')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Trạng thái</label>
                    <x-admin.status-select
                        name="status"
                        :options="\App\Enums\RecordStatus::options()"
                        selected="{{ $category->status }}"
                        class="form-select @error('status') is-invalid @enderror"
                    />
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Mô tả</label>
                    <textarea class="form-control @error('description') is-invalid @enderror" name="description" rows="4" placeholder="Mô tả về danh mục này...">{{ old('description', $category->description) }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('garment-categories.index') }}" class="btn btn-outline-secondary">Hủy</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Cập nhật
                </button>
            </div>
        </form>
    </div>
</div>
@endsection