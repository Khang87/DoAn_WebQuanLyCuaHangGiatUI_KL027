@extends('layouts.app')

@section('title', 'Chỉnh sửa dịch vụ - Sky Laundry')
@section('page-title', 'Chỉnh sửa dịch vụ')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">{{ $service->TenDichVu }}</h5>
            <a href="{{ route('garments.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('garments.update', $service->DichVuID) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Tên dịch vụ <span class="text-danger ms-1">*</span></label>
                    <input type="text" class="form-control @error('TenDichVu') is-invalid @enderror" name="TenDichVu" value="{{ old('TenDichVu', $service->TenDichVu) }}" required>
                    @error('TenDichVu')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Danh mục dịch vụ</label>
                    <select class="form-select @error('LoaiDichVuID') is-invalid @enderror" name="LoaiDichVuID">
                        <option value="">-- Chọn danh mục --</option>
                        @foreach($categories as $id => $name)
                            <option value="{{ $id }}" @selected(old('LoaiDichVuID', $service->LoaiDichVuID) == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('LoaiDichVuID')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Loại đồ giặt</label>
                    <select class="form-select @error('LoaiDoGiatID') is-invalid @enderror" name="LoaiDoGiatID">
                        <option value="">-- Chọn loại đồ --</option>
                        @foreach($garmentTypes as $id => $name)
                            <option value="{{ $id }}" @selected(old('LoaiDoGiatID', $service->LoaiDoGiatID) == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('LoaiDoGiatID')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Thời gian ước tính (phút) <span class="text-danger ms-1">*</span></label>
                    <input type="number" class="form-control @error('ThoiGianDuKien') is-invalid @enderror" name="ThoiGianDuKien" value="{{ old('ThoiGianDuKien', $service->ThoiGianDuKien) }}" min="1" required>
                    @error('ThoiGianDuKien')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Trạng thái</label>
                    <x-admin.status-select
                        name="TrangThai"
                        :options="\App\Enums\RecordStatus::options()"
                        :selected="$service->TrangThai"
                        class="form-select @error('TrangThai') is-invalid @enderror"
                    />
                    @error('TrangThai')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Mô tả dịch vụ</label>
                    <textarea class="form-control @error('MoTa') is-invalid @enderror" name="MoTa" rows="4" placeholder="Nhập mô tả chi tiết về dịch vụ...">{{ old('MoTa', $service->MoTa) }}</textarea>
                    @error('MoTa')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('garments.index') }}" class="btn btn-outline-secondary">Hủy</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Cập nhật
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
