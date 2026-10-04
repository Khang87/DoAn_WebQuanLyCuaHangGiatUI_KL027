@extends('layouts.app')
@section('title', 'Tạo thông báo - Sky Laundry')
@section('page-title', 'Tạo thông báo')
@section('content')
<div class="card">
    <div class="card-body">
        <form action="{{ route('notifications.store') }}" method="POST">
            @csrf
            <h5 class="mb-4">Nội dung thông báo</h5>

            <div class="mb-3">
                <label for="recipient" class="form-label">Người nhận <span class="text-danger ms-1">*</span></label>
                <select class="form-select @error('recipient') is-invalid @enderror" id="recipient" name="recipient" required>
                    <option value="">Chọn nhóm hoặc tài khoản</option>
                    @foreach($recipientGroups as $value => $label)
                        <option value="{{ $value }}" @selected(old('recipient') === $value)>{{ $label }}</option>
                    @endforeach
                    <optgroup label="Tài khoản cụ thể">
                        @foreach($users as $user)
                            <option value="{{ $user->TaiKhoanID }}" @selected((string) old('recipient') === (string) $user->TaiKhoanID)>
                                {{ $user->TenDangNhap }} ({{ $user->HoTen ?? $user->Email }})
                            </option>
                        @endforeach
                    </optgroup>
                </select>
                @error('recipient')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="LoaiThongBao" class="form-label">Loại thông báo</label>
                <input class="form-control" id="LoaiThongBao" name="LoaiThongBao" maxlength="100" value="{{ old('LoaiThongBao') }}">
            </div>
            <div class="mb-3">
                <label for="TieuDe" class="form-label">Tiêu đề <span class="text-danger ms-1">*</span></label>
                <input class="form-control @error('TieuDe') is-invalid @enderror" id="TieuDe" name="TieuDe" value="{{ old('TieuDe') }}" required>
                @error('TieuDe')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-3">
                <label for="NoiDung" class="form-label">Nội dung <span class="text-danger ms-1">*</span></label>
                <textarea class="form-control @error('NoiDung') is-invalid @enderror" id="NoiDung" name="NoiDung" rows="5" required>{{ old('NoiDung') }}</textarea>
                @error('NoiDung')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-3">
                <label for="DonHangID" class="form-label">Đơn hàng liên quan</label>
                <select class="form-select" id="DonHangID" name="DonHangID">
                    <option value="">Chọn đơn hàng</option>
                    @foreach($orders as $order)
                        <option value="{{ $order->DonHangID }}" @selected((string) old('DonHangID') === (string) $order->DonHangID)>
                            {{ $order->MaDonHang }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('notifications.index') }}" class="btn btn-outline-secondary">Hủy</a>
                <button type="submit" class="btn btn-primary">Đăng thông báo</button>
            </div>
        </form>
    </div>
</div>
@endsection
