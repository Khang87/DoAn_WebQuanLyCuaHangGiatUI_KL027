@extends('layouts.app')

@section('title', 'Thêm dịch vụ - Sky Laundry')
@section('page-title', 'Thêm dịch vụ')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Thông tin dịch vụ</h5>
            <a href="{{ route('services.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('services.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Danh mục <span class="text-danger ms-1">*</span></label>
                    <select id="LoaiDichVuID" class="form-select @error('LoaiDichVuID') is-invalid @enderror" name="LoaiDichVuID" required>
                        <option value="">Chọn danh mục</option>
                        @foreach($categories as $category)
                        <option value="{{ $category->LoaiDichVuID }}" @selected(old('LoaiDichVuID') == $category->LoaiDichVuID)>{{ $category->TenLoaiDichVu }}</option>
                        @endforeach
                    </select>
                    @error('LoaiDichVuID')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tên dịch vụ <span class="text-danger ms-1">*</span></label>
                    <input type="text" class="form-control @error('TenDichVu') is-invalid @enderror" name="TenDichVu" value="{{ old('TenDichVu') }}" placeholder="Nhập tên dịch vụ" maxlength="150" required>
                    @error('TenDichVu')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Thời gian dự kiến (phút)</label>
                    <input type="number" class="form-control @error('ThoiGianDuKien') is-invalid @enderror" name="ThoiGianDuKien" value="{{ old('ThoiGianDuKien') }}" min="0">
                    @error('ThoiGianDuKien')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trạng thái</label>
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
                    <label class="form-label">Mô tả chi tiết</label>
                    <textarea class="form-control @error('MoTa') is-invalid @enderror" name="MoTa" maxlength="500" rows="3" placeholder="Nhập mô tả chi tiết về dịch vụ...">{{ old('MoTa') }}</textarea>
                    @error('MoTa')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-outline-secondary">Làm mới</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Lưu dịch vụ
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
