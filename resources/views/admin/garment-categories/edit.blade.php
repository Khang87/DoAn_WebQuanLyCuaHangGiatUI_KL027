@extends('layouts.app')

@section('title', 'Chỉnh sửa danh mục loại đồ giặt - Sky Laundry')
@section('page-title', 'Chỉnh sửa danh mục loại đồ giặt')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Chỉnh sửa danh mục</h5>
            <a href="{{ route('garment-categories.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('garment-categories.update', $category) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label" for="TenDanhMuc">Tên danh mục <span class="text-danger">*</span></label>
                    <input
                        id="TenDanhMuc"
                        type="text"
                        maxlength="100"
                        class="form-control @error('TenDanhMuc') is-invalid @enderror"
                        name="TenDanhMuc"
                        value="{{ old('TenDanhMuc', $category->TenDanhMuc) }}"
                        required
                    >
                    @error('TenDanhMuc')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="TrangThai">Trạng thái</label>
                    <x-admin.status-select
                        name="TrangThai"
                        id="TrangThai"
                        :options="\App\Enums\RecordStatus::databaseOptions()"
                        :selected="$category->TrangThai"
                        class="form-select @error('TrangThai') is-invalid @enderror"
                    />
                    @error('TrangThai')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="MoTa">Mô tả</label>
                    <textarea id="MoTa" class="form-control @error('MoTa') is-invalid @enderror" name="MoTa" rows="3">{{ old('MoTa', $category->MoTa) }}</textarea>
                    @error('MoTa')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-outline-secondary">Khôi phục</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Cập nhật
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
