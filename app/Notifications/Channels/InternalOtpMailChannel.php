<?php

namespace App\Notifications\Channels;

use App\Notifications\InternalPasswordResetOtp;
use App\Services\OtpMailer;

class InternalOtpMailChannel
{
    public function __construct(private OtpMailer $mailer) {}

    public function send(object $notifiable, InternalPasswordResetOtp $notification): void
    {
        $this->mailer->send($notifiable->Email, $notification->otp, 10);
    }
}
