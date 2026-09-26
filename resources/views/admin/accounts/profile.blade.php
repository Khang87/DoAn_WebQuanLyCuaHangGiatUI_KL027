@extends('layouts.app')

@section('title', 'Hồ sơ cá nhân - Sky Laundry')
@section('page-title', 'Hồ sơ cá nhân')

@section('content')
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Thông tin cá nhân</h5>
        <a href="{{ route('settings') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-gear me-1"></i>Cài đặt
        </a>
    </div>
    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-3">
                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show mb-3">
                <i class="bi bi-x-circle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

@php
    $avatarUrl = (!empty($account->avatar) && file_exists(public_path($account->avatar)))
        ? asset($account->avatar)
        : asset('assets/images/user_' . (($account->id % 8) + 1) . '.jpg');
@endphp
        <div class="d-flex justify-content-center mb-4">
            <div class="position-relative d-inline-block">
                <label for="avatarInput" class="d-block" style="cursor: pointer;">
                    <img id="avatarPreview" src="{{ $avatarUrl }}" alt="Avatar" class="rounded-circle shadow-sm avatar-cover" style="width: 180px; height: 180px; border: 4px solid #e9ecef;">
                </label>
                <span class="position-absolute bottom-0 end-0 translate-middle badge rounded-circle bg-primary border-2 border-white" style="width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-camera" style="font-size: 14px;"></i>
                </span>
            </div>
        </div>

        <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
            @csrf @method('PUT')
            <input type="file" name="avatar" id="avatarInput" accept="image/*" class="d-none">
            <input type="hidden" name="remove_avatar" id="removeAvatarFlag" value="0">
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Họ và tên <span class="text-danger ms-1">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $account->name) }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email <span class="text-danger ms-1">*</span></label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email', $account->email) }}" required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Số điện thoại</label>
                    <input type="text" class="form-control @error('phone') is-invalid @enderror" name="phone" value="{{ old('phone', $account->phone) }}" placeholder="VD: 0909123456">
                    @error('phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Vai trò</label>
                    <input type="text" class="form-control" value="{{ $account->isManager() ? 'Quản lý' : ($account->role === 'staff' ? 'Nhân viên' : 'Khách hàng') }}" disabled>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Lưu thay đổi
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Đổi mật khẩu</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('profile.change-password') }}" method="POST">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Mật khẩu hiện tại <span class="text-danger ms-1">*</span></label>
                    <input type="password" class="form-control @error('current_password') is-invalid @enderror" name="current_password" required>
                    @error('current_password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mật khẩu mới <span class="text-danger ms-1">*</span></label>
                    <input type="password" class="form-control @error('new_password') is-invalid @enderror" name="new_password" required>
                    @error('new_password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text text-danger mt-1"><i class="bi bi-exclamation-triangle me-1"></i>Bỏ trống nếu không thay đổi mật khẩu</div>
                    @if(session('success'))
    <script>document.addEventListener('DOMContentLoaded', function() { document.querySelector('input[name="new_password"]').value = ''; });</script>
                    @endif
                </div>
                <div class="col-md-6">
                    <label class="form-label">Xác nhận mật khẩu mới <span class="text-danger ms-1">*</span></label>
                    <input type="password" class="form-control" name="new_password_confirmation" required>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-key me-1"></i>Đổi mật khẩu
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const avatarInput = document.getElementById('avatarInput');
        const avatarPreview = document.getElementById('avatarPreview');
        const removeAvatarFlag = document.getElementById('removeAvatarFlag');

        const notifyError = function (message) {
            if (typeof Swal === 'undefined') {
                window.alert(message);
                return;
            }
            Swal.fire({ icon: 'error', title: 'Ảnh không hợp lệ', text: message, confirmButtonText: 'Đã hiểu' });
        };

        avatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            if (!file.type.match('image.*')) {
                notifyError('Vui lòng chọn file hình ảnh.');
                this.value = '';
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                notifyError('Kích thước file không được vượt quá 2MB.');
                this.value = '';
                return;
            }

            // Preview instantly
            const reader = new FileReader();
            reader.onload = function(e) {
                avatarPreview.src = e.target.result;
            };
            reader.readAsDataURL(file);
            removeAvatarFlag.value = '0';

            // Upload via AJAX
            const formData = new FormData();
            formData.append('avatar', file);
            formData.append('_token', '{{ csrf_token() }}');

            fetch('{{ route('profile.avatar') }}', {
                method: 'POST',
                body: formData,
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Sync header (navbar) and sidebar avatar images
                    document.querySelectorAll('.sidebar-profile-img, .avatar-cover').forEach(function(img) {
                        img.src = data.avatar_url;
                    });
                } else {
                    notifyError(data.message || 'Tải ảnh lên thất bại.');
                }
            })
            .catch(function() {
                notifyError('Có lỗi xảy ra khi tải ảnh lên.');
            });
        });
    });
</script>
@endpush