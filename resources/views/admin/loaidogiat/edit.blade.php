@extends('layouts.app')

@section('title', 'Chỉnh sửa Loại đồ giặt - Sky Laundry')
@section('page-title', 'Chỉnh sửa Loại đồ giặt')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Chỉnh sửa loại đồ giặt: {{ $category->TenLoaiDoGiat }}</h5>
            <a href="{{ route('loaidogiat.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('loaidogiat.update', $category->LoaiDoGiatID) }}" method="POST">
            @csrf @method('PUT')
            <div class="row g-4">
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" for="DanhMucID">Danh mục <span class="text-danger ms-1">*</span></label>
                    <select class="form-select @error('DanhMucID') is-invalid @enderror" id="DanhMucID" name="DanhMucID" required>
                        <option value="">Chọn danh mục</option>
                        @foreach($categories as $parentCategory)
                            <option value="{{ $parentCategory->DanhMucID }}" @selected(old('DanhMucID', $category->DanhMucID) == $parentCategory->DanhMucID)>
                                {{ $parentCategory->TenDanhMuc }}
                            </option>
                        @endforeach
                    </select>
                    @error('DanhMucID')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">Tên loại đồ giặt <span class="text-danger ms-1">*</span></label>
                    <input type="text" class="form-control @error('TenLoaiDoGiat') is-invalid @enderror" name="TenLoaiDoGiat" value="{{ old('TenLoaiDoGiat', $category->TenLoaiDoGiat) }}" placeholder="VD: Áo, Quần, Váy, Chăn ga gối..." required>
                    @error('TenLoaiDoGiat')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">Trạng thái</label>
                    <x-admin.status-select
                        name="TrangThai"
                        :options="\App\Enums\RecordStatus::databaseOptions()"
                        :selected="$category->TrangThai"
                        class="form-select @error('TrangThai') is-invalid @enderror"
                    />
                    @error('TrangThai')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Mô tả</label>
                    <textarea class="form-control @error('MoTa') is-invalid @enderror" name="MoTa" rows="4" maxlength="255" placeholder="Mô tả loại đồ giặt...">{{ old('MoTa', $category->MoTa) }}</textarea>
                    @error('MoTa')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('loaidogiat.index') }}" class="btn btn-outline-secondary">Hủy</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Cập nhật
                </button>
            </div>
        </form>
    </div>
</div>
@endsection