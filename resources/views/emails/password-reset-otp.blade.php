<p>Mã OTP của bạn là: <strong>{{ $otp }}</strong></p>
<p>Mã có hiệu lực trong {{ $minutes }} phút. Nếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua email này.</p>
@if ($minutes === 10)
    <p><a href="{{ route('internal-password.reset') }}">Đặt lại mật khẩu nội bộ</a></p>
@endif
