@extends('layouts.app')

@section('title', 'Thêm Loại đồ giặt - Sky Laundry')
@section('page-title', 'Thêm Loại đồ giặt')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Thông tin danh mục</h5>
            <a href="{{ route('loaidogiat.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('loaidogiat.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Tên loại đồ giặt <span class="text-danger ms-1">*</span></label>
                    <input type="text" class="form-control @error('TenLoaiDoGiat') is-invalid @enderror" name="TenLoaiDoGiat" value="{{ old('TenLoaiDoGiat') }}" placeholder="VD: Áo, Quần, Váy, Chăn ga gối..." required>
                    @error('TenLoaiDoGiat')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Trạng thái</label>
                    <x-admin.status-select
                        name="TrangThai"
                        :options="\App\Enums\RecordStatus::databaseOptions()"
                        selected="Hoạt động"
                        class="form-select @error('TrangThai') is-invalid @enderror"
                    />
                    @error('TrangThai')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Mô tả</label>
                    <textarea class="form-control @error('MoTa') is-invalid @enderror" name="MoTa" rows="4" maxlength="255" placeholder="Mô tả loại đồ giặt...">{{ old('MoTa') }}</textarea>
                    @error('MoTa')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-outline-secondary">Làm mới</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Lưu loại đồ giặt
                </button>
            </div>
        </form>
    </div>
</div>
@endsection