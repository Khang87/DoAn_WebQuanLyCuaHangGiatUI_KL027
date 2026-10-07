<?php

namespace App\Notifications;

use App\Notifications\Channels\InternalOtpDatabaseChannel;
use App\Notifications\Channels\InternalOtpMailChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Synchronous delivery: never serialize a plaintext OTP into a queued job. */
class InternalPasswordResetOtp extends Notification
{
    public function __construct(public readonly string $otp) {}

    public function via(object $notifiable): array
    {
        return [InternalOtpDatabaseChannel::class, InternalOtpMailChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Mã OTP đặt lại mật khẩu nội bộ')
            ->line('Quản trị viên đã yêu cầu đặt lại mật khẩu cho tài khoản của bạn.')
            ->line('Mã OTP: '.$this->otp)
            ->line('Mã có hiệu lực trong 10 phút và chỉ được sử dụng một lần.')
            ->action('Đặt lại mật khẩu', route('internal-password.reset'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'TaiKhoanID' => $notifiable->getKey(),
            'LoaiThongBao' => 'internal_password_otp',
            'TieuDe' => 'OTP đặt lại mật khẩu nội bộ',
            'NoiDung' => 'Mã OTP: '.$this->otp.'. Có hiệu lực trong 10 phút, chỉ dùng một lần. Đặt lại mật khẩu tại '.route('internal-password.reset'),
            'ThoiGianGui' => now(),
            'DaDoc' => false,
        ];
    }
}
