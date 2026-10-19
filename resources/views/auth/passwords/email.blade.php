<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Quên mật khẩu - Sky Laundry</title>
    <link rel="stylesheet" href="{{ asset('assets/libs/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/libs/bootstrap-icons/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/laundry.css') }}">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-logo">
                <i class="bi bi-shield-lock" style="font-size: 3rem; color: var(--primary-blue);"></i>
                <h1>Quên mật khẩu?</h1>
                <p class="mb-2">Bước 1/2 · Nhập email và xác thực OTP</p>
                <p>Nhập email tài khoản đang hoạt động, sau đó xác nhận mã gồm 6 chữ số.</p>
            </div>

            @if(session('status'))
                <div class="alert alert-success" role="status">{{ session('status') }}</div>
            @endif
            @if(session('otp_privacy_notice'))
                <div class="alert alert-info" role="status">{{ session('otp_privacy_notice') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">Email tài khoản</label>
                    <input
                        class="form-control @error('email') is-invalid @enderror"
                        type="email"
                        id="email"
                        name="email"
                        value="{{ $email ?: old('email') }}"
                        maxlength="150"
                        autocomplete="email"
                        required
                        @if($email !== '') readonly @else autofocus @endif
                    >
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                @if($email !== '')
                    <button class="btn btn-outline-primary w-100" type="submit" data-otp-resend data-cooldown-seconds="{{ $cooldownSeconds }}" @disabled($cooldownSeconds > 0)>
                        {{ $cooldownSeconds > 0 ? 'Gửi lại mã ('.$cooldownSeconds.'s)' : 'Gửi lại mã OTP' }}
                    </button>
                    <noscript><p class="small text-muted mt-2">Sau 60 giây, tải lại trang để gửi mã mới.</p></noscript>
                @else
                    <button class="btn btn-primary w-100" type="submit">Gửi mã OTP</button>
                @endif
            </form>

            @if($email !== '')
                <p class="small text-muted mt-3">Mã OTP có hiệu lực trong 15 phút. Nếu email thuộc tài khoản đang hoạt động, hãy kiểm tra hộp thư và thư rác.</p>
                <form method="POST" action="{{ route('password.verify') }}">
                    @csrf
                    <input type="hidden" name="email" value="{{ $email }}">
                    <div class="mb-3">
                        <label class="form-label" for="otp">Mã OTP</label>
                        <input class="form-control @error('otp') is-invalid @enderror" type="text" id="otp" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus aria-describedby="otp-help">
                        <div id="otp-help" class="form-text">Nhập 6 chữ số trong email mới nhất.</div>
                        @error('otp')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Xác nhận</button>
                </form>
                <div class="text-center mt-3"><a href="{{ route('password.request', ['change_email' => 1]) }}">Đổi email</a></div>
            @endif

            <div class="text-center mt-3">
                <a href="{{ route('login') }}">Quay lại đăng nhập</a>
            </div>
        </div>
    </div>
    @vite('resources/js/password-reset.js')
</body>
</html>
