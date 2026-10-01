@extends('layouts.app')

@section('title', 'Chỉnh sửa khuyến mãi - Sky Laundry')
@section('page-title', 'Chỉnh sửa khuyến mãi')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">{{ $promotion->TenKhuyenMai }}</h5>
            <a href="{{ route('promotions.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('promotions.update', $promotion->KhuyenMaiID) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label" for="TenKhuyenMai">Tên chương trình <span class="text-danger">*</span></label>
                    <input type="text" id="TenKhuyenMai" name="TenKhuyenMai" maxlength="150" class="form-control @error('TenKhuyenMai') is-invalid @enderror" value="{{ old('TenKhuyenMai', $promotion->TenKhuyenMai) }}" required>
                    @error('TenKhuyenMai')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="MaKhuyenMai">Mã khuyến mãi <span class="text-danger">*</span></label>
                    <input type="text" id="MaKhuyenMai" name="MaKhuyenMai" maxlength="50" pattern="[A-Za-z0-9_-]+" class="form-control @error('MaKhuyenMai') is-invalid @enderror" value="{{ old('MaKhuyenMai', $promotion->MaKhuyenMai) }}" required>
                    @error('MaKhuyenMai')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="LoaiKhuyenMai">Loại giảm <span class="text-danger">*</span></label>
                    <select id="LoaiKhuyenMai" name="LoaiKhuyenMai" class="form-select @error('LoaiKhuyenMai') is-invalid @enderror" required>
                        @foreach(\App\Models\KhuyenMai::discountTypeOptions() as $value => $label)
                            <option value="{{ $value }}" @selected(old('LoaiKhuyenMai', $promotion->LoaiKhuyenMai) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('LoaiKhuyenMai')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="GiaTriGiam">Giá trị giảm <span class="text-danger">*</span></label>
                    <input type="number" id="GiaTriGiam" name="GiaTriGiam" min="0" step="0.01" class="form-control @error('GiaTriGiam') is-invalid @enderror" value="{{ old('GiaTriGiam', $promotion->GiaTriGiam) }}" required>
                    @error('GiaTriGiam')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="GiaTriDonToiThieu">Đơn hàng tối thiểu</label>
                    <input type="number" id="GiaTriDonToiThieu" name="GiaTriDonToiThieu" min="0" step="0.01" class="form-control @error('GiaTriDonToiThieu') is-invalid @enderror" value="{{ old('GiaTriDonToiThieu', $promotion->GiaTriDonToiThieu) }}">
                    @error('GiaTriDonToiThieu')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="MucGiamToiDa">Mức giảm tối đa</label>
                    <input type="number" id="MucGiamToiDa" name="MucGiamToiDa" min="0" step="0.01" class="form-control @error('MucGiamToiDa') is-invalid @enderror" value="{{ old('MucGiamToiDa', $promotion->MucGiamToiDa) }}">
                    @error('MucGiamToiDa')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="DieuKienApDung">Điều kiện áp dụng</label>
                    <input type="hidden" name="DieuKienApDung" value="">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="first_order_only" id="first_order_only" value="1" @checked(old('DieuKienApDung', $promotion->DieuKienApDung) === 'first_order_only' || old('first_order_only') === '1')>
                        <label class="form-check-label" for="first_order_only">Chỉ áp dụng cho đơn đầu tiên của khách</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="NgayBatDau">Ngày bắt đầu <span class="text-danger">*</span></label>
                    <input type="date" id="NgayBatDau" name="NgayBatDau" class="form-control @error('NgayBatDau') is-invalid @enderror" value="{{ old('NgayBatDau', $promotion->NgayBatDau?->format('Y-m-d')) }}" required>
                    @error('NgayBatDau')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="NgayKetThuc">Ngày kết thúc <span class="text-danger">*</span></label>
                    <input type="date" id="NgayKetThuc" name="NgayKetThuc" class="form-control @error('NgayKetThuc') is-invalid @enderror" value="{{ old('NgayKetThuc', $promotion->NgayKetThuc?->format('Y-m-d')) }}" required>
                    @error('NgayKetThuc')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="TrangThai">Trạng thái <span class="text-danger">*</span></label>
                    <x-admin.status-select
                        name="TrangThai"
                        id="TrangThai"
                        :options="['Hoạt động' => 'Hoạt động', 'Tạm ngưng' => 'Tạm ngưng', 'Hết hạn' => 'Hết hạn']"
                        :selected="$promotion->TrangThai"
                        class="form-select @error('TrangThai') is-invalid @enderror"
                        required
                    />
                    @error('TrangThai')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('promotions.index') }}" class="btn btn-outline-secondary">Hủy</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Lưu thay đổi</button>
            </div>
        </form>
    </div>
</div>
@endsection
