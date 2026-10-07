<?php

namespace App\Notifications\Channels;

use App\Notifications\InternalPasswordResetOtp;
use App\Services\ResendOtpMailer;

class InternalOtpMailChannel
{
    public function __construct(private ResendOtpMailer $mailer) {}

    public function send(object $notifiable, InternalPasswordResetOtp $notification): void
    {
        $this->mailer->send($notifiable->Email, $notification->otp, 10);
    }
}
