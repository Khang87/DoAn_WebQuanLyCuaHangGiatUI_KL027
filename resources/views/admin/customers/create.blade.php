@extends('layouts.app')

@section('title', 'Thêm khách hàng - Sky Laundry')
@section('page-title', 'Thêm khách hàng')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Thông tin khách hàng</h5>
            <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('customers.store') }}" method="POST" id="customer-create-form">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Họ tên <span class="text-danger ms-1">*</span></label>
                    <input type="text" class="form-control @error('HoTen') is-invalid @enderror" name="HoTen" value="{{ old('HoTen') }}" placeholder="Nhập họ tên" required>
                    @error('HoTen')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email <span class="text-muted small">(không bắt buộc nếu đã nhập số điện thoại)</span></label>
                    <input type="email" class="form-control @error('Email') is-invalid @enderror" name="Email" value="{{ old('Email') }}" placeholder="Nhập email">
                    @error('Email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Số điện thoại <span class="text-muted small">(không bắt buộc nếu đã nhập email)</span></label>
                    <input type="text" class="form-control @error('SoDienThoai') is-invalid @enderror" name="SoDienThoai" value="{{ old('SoDienThoai') }}" placeholder="Nhập số điện thoại">
                    @error('SoDienThoai')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <div class="form-text" id="customer-contact-help">Vui lòng nhập ít nhất một trong hai thông tin: số điện thoại hoặc email.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Điểm tích lũy</label>
                    <input type="number" class="form-control @error('DiemHienTai') is-invalid @enderror" name="DiemHienTai" value="0" min="0" placeholder="Điểm tích lũy">
                    @error('DiemHienTai')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Địa chỉ</label>
                    <textarea class="form-control @error('DiaChi') is-invalid @enderror" name="DiaChi" rows="3" placeholder="Nhập địa chỉ"></textarea>
                    @error('DiaChi')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-outline-secondary">Làm mới</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Lưu khách hàng
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('customer-create-form');
        if (!form) {
            return;
        }

        const email = form.elements.Email;
        const phone = form.elements.SoDienThoai;
        const message = 'Vui lòng nhập số điện thoại hoặc email.';
        const updateContactValidity = function () {
            const hasContact = email.value.trim() !== '' || phone.value.trim() !== '';
            email.setCustomValidity(hasContact ? '' : message);
            phone.setCustomValidity(hasContact ? '' : message);
        };

        email.addEventListener('input', updateContactValidity);
        phone.addEventListener('input', updateContactValidity);
        form.addEventListener('submit', function (event) {
            updateContactValidity();
            if (!form.checkValidity()) {
                event.preventDefault();
                form.reportValidity();
            }
        });
    });
</script>
@endpush