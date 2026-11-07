<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class BookingTimeWindow
{
    public const MESSAGE = 'Thời gian đặt lịch phải nằm trong khung giờ từ 08:00 đến 17:00.';

    public static function allows(mixed $time): bool
    {
        if (! is_string($time) || preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/D', $time) !== 1) {
            return false;
        }

        $normalizedTime = strlen($time) === 5 ? $time.':00' : $time;

        return $normalizedTime >= '08:00:00' && $normalizedTime <= '17:00:00';
    }

    public static function assertAllowed(mixed $time, string $field = 'scheduled_time'): void
    {
        if (! self::allows($time)) {
            throw ValidationException::withMessages([$field => self::MESSAGE]);
        }
    }
}
