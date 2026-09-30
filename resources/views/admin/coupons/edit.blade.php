@extends('layouts.app')

@section('title', 'Chỉnh sửa mã giảm giá - Sky Laundry')
@section('page-title', 'Chỉnh sửa mã giảm giá')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Chỉnh sửa mã giảm giá</h5>
            <a href="{{ route('coupons.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('coupons.update', $coupon->KhuyenMaiID) }}" method="POST">
            @csrf @method('PUT')
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Tên khuyến mãi <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('TenKhuyenMai') is-invalid @enderror" name="TenKhuyenMai" value="{{ old('TenKhuyenMai', $coupon->TenKhuyenMai) }}" required>
                    @error('TenKhuyenMai')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mã coupon <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('MaKhuyenMai') is-invalid @enderror" name="MaKhuyenMai" value="{{ old('MaKhuyenMai', $coupon->MaKhuyenMai) }}" required>
                    @error('MaKhuyenMai')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Loại giảm giá <span class="text-danger">*</span></label>
                    <select class="form-select @error('LoaiKhuyenMai') is-invalid @enderror" name="LoaiKhuyenMai" required>
                        <option value="Phần trăm" {{ $coupon->LoaiKhuyenMai === 'Phần trăm' ? 'selected' : '' }}>Phần trăm (%)</option>
                        <option value="Tiền mặt" {{ $coupon->LoaiKhuyenMai === 'Tiền mặt' ? 'selected' : '' }}>Số tiền cố định (VNĐ)</option>
                    </select>
                    @error('LoaiKhuyenMai')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Giá trị giảm giá <span class="text-danger">*</span></label>
                    <input type="number" class="form-control @error('GiaTriGiam') is-invalid @enderror" name="GiaTriGiam" value="{{ old('GiaTriGiam', $coupon->GiaTriGiam) }}" min="0" step="0.01" required>
                    @error('GiaTriGiam')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Giá trị đơn tối thiểu</label>
                    <input type="number" class="form-control" name="GiaTriDonToiThieu" value="{{ old('GiaTriDonToiThieu', $coupon->GiaTriDonToiThieu) }}" min="0" step="0.01">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mức giảm tối đa</label>
                    <input type="number" class="form-control" name="MucGiamToiDa" value="{{ old('MucGiamToiDa', $coupon->MucGiamToiDa) }}" min="0" step="0.01">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Số lượng mã phát hành</label>
                    <input type="number" class="form-control" name="SoLuongSuDung" value="{{ old('SoLuongSuDung', $coupon->SoLuongSuDung) }}" min="0">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Điều kiện áp dụng</label>
                    <input type="text" class="form-control" name="DieuKienApDung" value="{{ old('DieuKienApDung', $coupon->DieuKienApDung) }}" placeholder="vd: first_order_only">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ngày bắt đầu <span class="text-danger">*</span></label>
                    <input type="date" class="form-control @error('NgayBatDau') is-invalid @enderror" name="NgayBatDau" value="{{ old('NgayBatDau', $coupon->NgayBatDau) }}" required>
                    @error('NgayBatDau')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ngày kết thúc <span class="text-danger">*</span></label>
                    <input type="date" class="form-control @error('NgayKetThuc') is-invalid @enderror" name="NgayKetThuc" value="{{ old('NgayKetThuc', $coupon->NgayKetThuc) }}" required>
                    @error('NgayKetThuc')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trạng thái</label>
                    <x-admin.status-select
                        name="TrangThai"
                        :options="\App\Enums\RecordStatus::options()"
                        :selected="$coupon->TrangThai"
                        class="form-select @error('TrangThai') is-invalid @enderror"
                    />
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-outline-secondary">Làm mới</button>
                <button type="submit" class="btn btn-primary">Cập nhật</button>
            </div>
        </form>
    </div>
</div>
@endsection
