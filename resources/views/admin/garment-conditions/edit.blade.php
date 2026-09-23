@extends('layouts.app')

@section('title', 'Chỉnh sửa điều kiện - Giặt Ủi Pro')
@section('page-title', 'Chỉnh sửa điều kiện')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Chỉnh sửa điều kiện</h5>
            <a href="{{ route('garment-conditions.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('garment-conditions.update', $condition->id) }}" method="POST">
            @csrf @method('PUT')
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Loại đồ giặt <span class="text-danger">*</span></label>
                    <select class="form-select @error('garment_id') is-invalid @enderror" name="garment_id" required>
                        <option value="">Chọn loại đồ</option>
                        @foreach($garments as $garment)
                        <option value="{{ $garment->id }}" {{ $condition->garment_id === $garment->id ? 'selected' : '' }}>{{ $garment->name }}</option>
                        @endforeach
                    </select>
                    @error('garment_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Loại hiện trạng <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('condition_type') is-invalid @enderror" name="condition_type" value="{{ old('condition_type', $condition->condition_type) }}" required>
                    @error('condition_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Mô tả</label>
                    <textarea class="form-control @error('description') is-invalid @enderror" name="description" rows="3">{{ old('description', $condition->description) }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ảnh</label>
                    <input type="text" class="form-control" name="photo" value="{{ old('photo', $condition->photo) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trạng thái</label>
                    <select class="form-select" name="status">
                        <option value="active" {{ $condition->status === 'active' ? 'selected' : '' }}>Hoạt động</option>
                        <option value="inactive" {{ $condition->status === 'inactive' ? 'selected' : '' }}>Tắt</option>
                    </select>
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
