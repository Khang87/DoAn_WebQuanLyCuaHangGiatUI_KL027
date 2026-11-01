<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Nhập OTP - Sky Laundry</title>
    <link rel="stylesheet" href="{{ asset('assets/libs/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/libs/bootstrap-icons/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/laundry.css') }}">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-logo">
                <i class="bi bi-shield-lock" style="font-size: 3rem; color: var(--primary-blue);"></i>
                <h1>Đặt lại mật khẩu</h1>
                <p>Nhập mã OTP và mật khẩu mới cho tài khoản của bạn.</p>
            </div>

            @if(session('status'))
                <div class="alert alert-success" role="status">{{ session('status') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route($submitRoute ?? 'password.update') }}">
                @csrf
                @unless($internalReset ?? false)
                    <div class="mb-3">
                        <label class="form-label" for="email">Email tài khoản</label>
                        <input
                            class="form-control @error('email') is-invalid @enderror"
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email', $email) }}"
                            maxlength="150"
                            autocomplete="email"
                            required
                        >
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endunless
                <div class="mb-3">
                    <label class="form-label" for="otp">Mã OTP</label>
                    <input
                        class="form-control @error('otp') is-invalid @enderror"
                        type="text"
                        id="otp"
                        name="otp"

                        inputmode="numeric"
                        pattern="[0-9]{6}"
                        maxlength="6"
                        autocomplete="one-time-code"
                        required
                        autofocus
                    >
                    @error('otp')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Mật khẩu mới</label>
                    <div class="input-group">
                        <input
                            class="form-control @error('password') is-invalid @enderror"
                            type="password"
                            id="password"
                            name="password"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >
                        <button
                            class="btn btn-outline-secondary"
                            type="button"
                            data-toggle-password="password"
                            aria-label="Hiện mật khẩu"
                            aria-controls="password"
                            aria-pressed="false"
                        >
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password_confirmation">Xác nhận mật khẩu mới</label>
                    <div class="input-group">
                        <input
                            class="form-control"
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >
                        <button
                            class="btn btn-outline-secondary"
                            type="button"
                            data-toggle-password="password_confirmation"
                            aria-label="Hiện mật khẩu"
                            aria-controls="password_confirmation"
                            aria-pressed="false"
                        >
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <button class="btn btn-primary w-100" type="submit">Cập nhật mật khẩu</button>
            </form>

            <div class="text-center mt-3">
                @if($internalReset ?? false)
                    <p>Liên hệ quản trị viên để yêu cầu mã OTP mới.</p>
                @else
                    <a href="{{ route('password.request') }}">Yêu cầu mã OTP mới</a>
                @endif
            </div>
        </div>
    </div>
    @vite('resources/js/password-reset.js')
</body>
</html>
