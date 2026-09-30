@extends('layouts.app')

@section('title', 'Thêm mã giảm giá - Sky Laundry')
@section('page-title', 'Thêm mã giảm giá')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Mã giảm giá</h5>
            <a href="{{ route('coupons.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('coupons.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Tên khuyến mãi <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('TenKhuyenMai') is-invalid @enderror" name="TenKhuyenMai" value="{{ old('TenKhuyenMai') }}" placeholder="vd: Giảm giá VIP" required>
                    @error('TenKhuyenMai')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mã coupon <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('MaKhuyenMai') is-invalid @enderror" name="MaKhuyenMai" value="{{ old('MaKhuyenMai') }}" placeholder="vd: VIP20" required>
                    @error('MaKhuyenMai')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Loại giảm giá <span class="text-danger">*</span></label>
                    <select class="form-select @error('LoaiKhuyenMai') is-invalid @enderror" name="LoaiKhuyenMai" required>
                        <option value="Phần trăm">Phần trăm (%)</option>
                        <option value="Tiền mặt">Số tiền cố định (VNĐ)</option>
                    </select>
                    @error('LoaiKhuyenMai')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Giá trị giảm giá <span class="text-danger">*</span></label>
                    <input type="number" class="form-control @error('GiaTriGiam') is-invalid @enderror" name="GiaTriGiam" value="{{ old('GiaTriGiam') }}" min="0" step="0.01" required>
                    @error('GiaTriGiam')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Giá trị đơn tối thiểu</label>
                    <input type="number" class="form-control" name="GiaTriDonToiThieu" value="{{ old('GiaTriDonToiThieu') }}" min="0" step="0.01">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mức giảm tối đa</label>
                    <input type="number" class="form-control" name="MucGiamToiDa" value="{{ old('MucGiamToiDa') }}" min="0" step="0.01">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Số lượng mã phát hành</label>
                    <input type="number" class="form-control" name="SoLuongSuDung" value="{{ old('SoLuongSuDung') }}" min="0">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Điều kiện áp dụng</label>
                    <input type="text" class="form-control" name="DieuKienApDung" value="{{ old('DieuKienApDung') }}" placeholder="vd: first_order_only">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ngày bắt đầu <span class="text-danger">*</span></label>
                    <input type="date" class="form-control @error('NgayBatDau') is-invalid @enderror" name="NgayBatDau" value="{{ old('NgayBatDau') }}" required>
                    @error('NgayBatDau')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ngày kết thúc <span class="text-danger">*</span></label>
                    <input type="date" class="form-control @error('NgayKetThuc') is-invalid @enderror" name="NgayKetThuc" value="{{ old('NgayKetThuc') }}" required>
                    @error('NgayKetThuc')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trạng thái</label>
                    <x-admin.status-select
                        name="TrangThai"
                        :options="\App\Enums\RecordStatus::options()"
                        selected="Hoạt động"
                        class="form-select @error('TrangThai') is-invalid @enderror"
                    />
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-outline-secondary">Làm mới</button>
                <button type="submit" class="btn btn-primary">Tạo</button>
            </div>
        </form>
    </div>
</div>
@endsection
