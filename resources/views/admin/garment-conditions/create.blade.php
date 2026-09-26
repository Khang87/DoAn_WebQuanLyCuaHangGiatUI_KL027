@extends('layouts.app')

@section('title', 'Thêm điều kiện - Sky Laundry')
@section('page-title', 'Thêm điều kiện')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Điều kiện đồ giặt</h5>
            <a href="{{ route('garment-conditions.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('garment-conditions.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Loại đồ giặt <span class="text-danger">*</span></label>
                    <select class="form-select @error('garment_id') is-invalid @enderror" name="garment_id" required>
                        <option value="">-- Chọn loại đồ --</option>
                        @foreach($garments as $garment)
                        <option value="{{ $garment->id }}">{{ $garment->name }}</option>
                        @endforeach
                    </select>
                    @error('garment_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Loại hiện trạng <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('condition_type') is-invalid @enderror" name="condition_type" value="{{ old('condition_type') }}" placeholder="vd: Rách, Bẩn nặng..." required>
                    @error('condition_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Mô tả</label>
                    <textarea class="form-control @error('description') is-invalid @enderror" name="description" rows="3">{{ old('description') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ảnh hiện trạng</label>
                    <input type="text" class="form-control @error('photo') is-invalid @enderror" name="photo" value="{{ old('photo') }}" placeholder="URL ảnh">
                    @error('photo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trạng thái</label>
                    <x-admin.status-select
                        name="status"
                        :options="\App\Enums\RecordStatus::options()"
                        selected="active"
                        class="form-select @error('status') is-invalid @enderror"
                    />
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-outline-secondary">Làm mới</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Thêm
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
