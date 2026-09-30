@extends('layouts.app')

@section('title', 'Thêm danh mục - Sky Laundry')
@section('page-title', 'Thêm danh mục')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Thông tin danh mục</h5>
            <a href="{{ route('service-categories.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('service-categories.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Tên danh mục <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('TenLoaiDichVu') is-invalid @enderror" name="TenLoaiDichVu" value="{{ old('TenLoaiDichVu') }}" required>
                    @error('TenLoaiDichVu')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trạng thái</label>
                    <x-admin.status-select
                        name="TrangThai"
                        :options="\App\Enums\RecordStatus::options()"
                        selected="Hoạt động"
                        class="form-select @error('TrangThai') is-invalid @enderror"
                    />
                    @error('TrangThai')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Mô tả</label>
                    <textarea class="form-control @error('MoTa') is-invalid @enderror" name="MoTa" rows="3">{{ old('MoTa') }}</textarea>
                    @error('MoTa')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-outline-secondary">Làm mới</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Tạo danh mục
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
