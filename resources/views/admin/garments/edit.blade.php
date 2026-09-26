@extends('layouts.app')

@section('title', 'Chỉnh sửa loại Đồ giặt - Sky Laundry')
@section('page-title', 'Chỉnh sửa loại Đồ giặt')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">{{ $garment->name }}</h5>
            <a href="{{ route('garments.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('garments.update', $garment->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Tên loại đồ giặt / sản phẩm <span class="text-danger ms-1">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $garment->name) }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Danh mục loại đồ</label>
                    <select class="form-select @error('category') is-invalid @enderror" name="category">
                        <option value="">-- Chọn danh mục --</option>
                        @foreach($categories as $id => $name)
                            <option value="{{ $id }}" @selected(old('category', $garment->garment_category_id ?? $garment->category) == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('category')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Đơn giá dịch vụ (VNĐ) <span class="text-danger ms-1">*</span></label>
                    <input type="number" class="form-control @error('price') is-invalid @enderror" name="price" value="{{ old('price', (int) round((float) $garment->price)) }}" min="0" required>
                    @error('price')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Trạng thái</label>
                    <x-admin.status-select
                        name="status"
                        :options="\App\Enums\RecordStatus::options()"
                        :selected="$garment->status"
                        class="form-select @error('status') is-invalid @enderror"
                    />
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Mô tả hiện trạng trước khi giặt (Ghi chú rách, ố, cũ, sờn...)</label>
                    <textarea class="form-control @error('condition_note') is-invalid @enderror" name="condition_note" rows="4" placeholder="Nhập mô tả tình trạng sản phẩm trước khi tiếp nhận giặt...">{{ old('condition_note', $garment->condition_note) }}</textarea>
                    @error('condition_note')
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
