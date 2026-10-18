<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mật khẩu mới - Sky Laundry</title>
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
                <p>Bước 2/2 · Đặt mật khẩu mới</p>
                <p>Email đã được xác thực. Mật khẩu cần ít nhất 8 ký tự, có chữ hoa, chữ thường, số, ký tự đặc biệt và khác mật khẩu hiện tại.</p>
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

            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="password">Mật khẩu mới</label>
                    <input
                        class="form-control @error('password') is-invalid @enderror"
                        type="password"
                        id="password"
                        name="password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password_confirmation">Xác nhận mật khẩu mới</label>
                    <input
                        class="form-control"
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >
                </div>
                <button class="btn btn-primary w-100" type="submit">Cập nhật mật khẩu</button>
            </form>

            <div class="text-center mt-3">
                <a href="{{ route('password.request', ['change_email' => 1]) }}">Bắt đầu lại</a>
            </div>
        </div>
    </div>
</body>
</html>
