@extends('layouts.app')

@section('title', 'Thêm khuyến mãi - Sky Laundry')
@section('page-title', 'Thêm khuyến mãi')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Thông tin chương trình</h5>
            <a href="{{ route('promotions.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('promotions.store') }}" method="POST">
            @csrf
            <section class="rounded-3 border p-3 p-md-4 mb-4">
                <div class="mb-3">
                    <h6 class="fw-bold mb-1"><i class="bi bi-card-text text-primary me-2"></i>Thông tin chương trình</h6>
                    <p class="text-muted small mb-0">Tên và mã giúp nhân viên nhận diện chương trình khuyến mãi.</p>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="TenKhuyenMai">Tên chương trình <span class="text-danger">*</span></label>
                        <input type="text" id="TenKhuyenMai" name="TenKhuyenMai" maxlength="150" class="form-control @error('TenKhuyenMai') is-invalid @enderror" value="{{ old('TenKhuyenMai') }}" required>
                        @error('TenKhuyenMai')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="MaKhuyenMai">Mã khuyến mãi <span class="text-danger">*</span></label>
                        <input type="text" id="MaKhuyenMai" name="MaKhuyenMai" maxlength="50" pattern="[A-Za-z0-9_-]+" class="form-control @error('MaKhuyenMai') is-invalid @enderror" value="{{ old('MaKhuyenMai') }}" required>
                        @error('MaKhuyenMai')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </section>

            <section class="rounded-3 border p-3 p-md-4 mb-4">
                <div class="mb-3">
                    <h6 class="fw-bold mb-1"><i class="bi bi-percent text-primary me-2"></i>Mức ưu đãi</h6>
                    <p class="text-muted small mb-0">Thiết lập cách tính ưu đãi và điều kiện áp dụng cho đơn hàng.</p>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="LoaiKhuyenMai">Loại giảm <span class="text-danger">*</span></label>
                        <select id="LoaiKhuyenMai" name="LoaiKhuyenMai" class="form-select @error('LoaiKhuyenMai') is-invalid @enderror" required>
                            @foreach(\App\Models\KhuyenMai::discountTypeOptions() as $value => $label)
                                <option value="{{ $value }}" @selected(old('LoaiKhuyenMai', \App\Models\KhuyenMai::DISCOUNT_PERCENTAGE) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('LoaiKhuyenMai')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="GiaTriGiam">Giá trị giảm <span class="text-danger">*</span></label>
                        <input type="number" id="GiaTriGiam" name="GiaTriGiam" min="0" step="0.01" class="form-control @error('GiaTriGiam') is-invalid @enderror" value="{{ old('GiaTriGiam') }}" required>
                        @error('GiaTriGiam')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="GiaTriDonToiThieu">Đơn hàng tối thiểu</label>
                        <input type="number" id="GiaTriDonToiThieu" name="GiaTriDonToiThieu" min="0" step="0.01" class="form-control @error('GiaTriDonToiThieu') is-invalid @enderror" value="{{ old('GiaTriDonToiThieu') }}">
                        @error('GiaTriDonToiThieu')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="MucGiamToiDa">Mức giảm tối đa</label>
                        <input type="number" id="MucGiamToiDa" name="MucGiamToiDa" min="0" step="0.01" class="form-control @error('MucGiamToiDa') is-invalid @enderror" value="{{ old('MucGiamToiDa') }}">
                        @error('MucGiamToiDa')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </section>

            <section class="rounded-3 border p-3 p-md-4">
                <div class="mb-3">
                    <h6 class="fw-bold mb-1"><i class="bi bi-calendar-range text-primary me-2"></i>Thời hạn &amp; trạng thái</h6>
                    <p class="text-muted small mb-0">Chọn khoảng thời gian chương trình được áp dụng và trạng thái hiện tại.</p>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="NgayBatDau">Ngày bắt đầu <span class="text-danger">*</span></label>
                        <input type="date" id="NgayBatDau" name="NgayBatDau" class="form-control @error('NgayBatDau') is-invalid @enderror" value="{{ old('NgayBatDau') }}" required>
                        @error('NgayBatDau')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="NgayKetThuc">Ngày kết thúc <span class="text-danger">*</span></label>
                        <input type="date" id="NgayKetThuc" name="NgayKetThuc" class="form-control @error('NgayKetThuc') is-invalid @enderror" value="{{ old('NgayKetThuc') }}" required>
                        @error('NgayKetThuc')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="TrangThai">Trạng thái <span class="text-danger">*</span></label>
                        <x-admin.status-select
                            name="TrangThai"
                            id="TrangThai"
                            :options="['Hoạt động' => 'Hoạt động', 'Tạm ngưng' => 'Tạm ngưng', 'Hết hạn' => 'Hết hạn']"
                            :selected="old('TrangThai', 'Hoạt động')"
                            class="form-select @error('TrangThai') is-invalid @enderror"
                            required
                        />
                        @error('TrangThai')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </section>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('promotions.index') }}" class="btn btn-outline-secondary">Hủy</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Lưu khuyến mãi</button>
            </div>
        </form>
    </div>
</div>
@endsection
