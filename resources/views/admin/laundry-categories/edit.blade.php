@extends('layouts.app')

@section('title', 'Chỉnh sửa danh mục loại đồ - Sky Laundry')
@section('page-title', 'Chỉnh sửa danh mục loại đồ')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Chỉnh sửa danh mục</h5>
            <a href="{{ route('laundry-categories.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('laundry-categories.update', $category->getKey()) }}" method="POST">
            @csrf @method('PUT')
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Tên danh mục <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('TenLoaiDichVu') is-invalid @enderror" name="TenLoaiDichVu" value="{{ old('TenLoaiDichVu', $category->TenLoaiDichVu) }}" required>
                    @error('TenLoaiDichVu')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trạng thái <span class="text-danger">*</span></label>
                    <select name="TrangThai" class="form-select @error('TrangThai') is-invalid @enderror" required>
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('TrangThai', $category->TrangThai) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('TrangThai')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Mô tả</label>
                    <textarea class="form-control @error('MoTa') is-invalid @enderror" name="MoTa" rows="3">{{ old('MoTa', $category->MoTa) }}</textarea>
                    @error('MoTa')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-outline-secondary">Làm mới</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Cập nhật
                </button>
            </div>
        </form>
    </div>
</div>
@endsection