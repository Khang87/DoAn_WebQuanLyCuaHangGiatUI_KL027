<?php

namespace App\Notifications\Channels;

use App\Models\ThongBao;
use App\Notifications\InternalPasswordResetOtp;

class InternalOtpDatabaseChannel
{
    public function send(object $notifiable, InternalPasswordResetOtp $notification): void
    {
        // Laravel's stock database channel expects a different, absent table.
        ThongBao::query()->create($notification->toDatabase($notifiable));
    }
}
