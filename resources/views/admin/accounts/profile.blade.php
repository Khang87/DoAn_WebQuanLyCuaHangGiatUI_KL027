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
        <div class="d-flex justify-content-center mb-4 position-relative">
            <img id="avatarPreview" src="{{ $avatarUrl }}" alt="Avatar" class="rounded-circle shadow-sm avatar-cover" style="width: 180px; height: 180px; border: 4px solid #e9ecef;">
            <label for="avatarInput" class="btn btn-sm btn-outline-primary position-absolute bottom-0 end-0 m-2" style="border-radius: 50%; width: 40px; height: 40px; padding: 0; display: flex; align-items: center; justify-content: center;" title="Đổi avatar">
                <i class="bi bi-camera"></i>
            </label>
        </div>

        <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
            @csrf @method('PUT')
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
                    <label class="form-label">Avatar</label>
                    <input type="file" class="form-control @error('avatar') is-invalid @enderror" name="avatar" id="avatarInput" accept="image/*">
                    @error('avatar')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Chấp nhận JPG, PNG, GIF. Tối đa 2MB.</div>
                    <input type="hidden" name="remove_avatar" id="removeAvatarFlag" value="0">
                    <button type="button" class="btn btn-sm btn-outline-danger mt-2" id="removeAvatarBtn" style="display: none;">
                        <i class="bi bi-trash me-1"></i>Xóa avatar
                    </button>
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
        const removeAvatarBtn = document.getElementById('removeAvatarBtn');
        const removeAvatarFlag = document.getElementById('removeAvatarFlag');

        // Preview avatar on file select
        const notifyError = function (message) {
            if (typeof Swal === 'undefined') {
                window.alert(message);
                return;
            }
            Swal.fire({ icon: 'error', title: 'Ảnh không hợp lệ', text: message, confirmButtonText: 'Đã hiểu' });
        };

        avatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
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
                const reader = new FileReader();
                reader.onload = function(e) {
                    avatarPreview.src = e.target.result;
                };
                reader.readAsDataURL(file);
                removeAvatarFlag.value = '0';
                removeAvatarBtn.style.display = 'inline-block';
            }
        });

        // Remove avatar
        removeAvatarBtn.addEventListener('click', function() {
            avatarInput.value = '';
            avatarPreview.src = avatarPreview.dataset.defaultSrc || '{{ asset("assets/images/user_" . (($account->id % 8) + 1) . ".jpg") }}';
            removeAvatarFlag.value = '1';
            removeAvatarBtn.style.display = 'none';
        });
    });
</script>
@endpush