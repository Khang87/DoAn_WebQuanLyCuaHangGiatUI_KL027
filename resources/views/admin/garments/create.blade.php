@extends('layouts.app')

@section('title', 'Thêm Loại Đồ Giặt - Sky Laundry')
@section('page-title', 'Thêm Loại Đồ Giặt & Hiện Trạng')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Thông tin loại đồ giặt</h5>
            <a href="{{ route('garments.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('garments.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Tên loại đồ giặt / sản phẩm</label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" placeholder="VD: Áo dài, Váy cưới, Áo khoác dạ..." required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Danh mục loại đồ</label>
                    <select class="form-select @error('category') is-invalid @enderror" name="category">
                        <option value="">Chọn danh mục</option>
                        <option value="áo dài" @selected(old('category') === 'áo dài')>Áo dài</option>
                        <option value="váy cưới" @selected(old('category') === 'váy cưới')>Váy cưới</option>
                        <option value="áo khoác" @selected(old('category') === 'áo khoác')>Áo khoác</option>
                        <option value="quần âu" @selected(old('category') === 'quần âu')>Quần âu</option>
                        <option value="đồ thường" @selected(old('category') === 'đồ thường')>Đồ thường</option>
                        <option value="chăn mền" @selected(old('category') === 'chăn mền')>Chăn mền</option>
                        <option value="giày dép" @selected(old('category') === 'giày dép')>Giày dép</option>
                    </select>
                    @error('category')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Đơn giá dịch vụ (VNĐ)</label>
                    <input type="number" class="form-control @error('price') is-invalid @enderror" name="price" value="{{ old('price') }}" placeholder="50000" min="0" required>
                    @error('price')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Trạng thái</label>
                    <select class="form-select @error('status') is-invalid @enderror" name="status">
                        <option value="active" @selected(old('status', 'active') === 'active')>Đang hoạt động</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>Tạm ngưng</option>
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Mô tả hiện trạng trước khi giặt (Ghi chú rách, ố, cũ, sờn...)</label>
                    <textarea class="form-control @error('condition_note') is-invalid @enderror" name="condition_note" rows="4" placeholder="Nhập mô tả tình trạng sản phẩm trước khi tiếp nhận giặt...">{{ old('condition_note') }}</textarea>
                    @error('condition_note')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-outline-secondary">Làm mới</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Lưu thông tin
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
