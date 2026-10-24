<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Bốn mốc thời gian dùng chung cho Dashboard và Báo cáo.
 *
 * Mọi số liệu trên hai trang này phải cùng một khoảng thời gian nên việc quy
 * đổi mốc, nhãn hiển thị và ngày đầu/cuối được gom vào một chỗ.
 */
class TimePeriod
{
    public const TODAY = 'today';

    public const THIS_WEEK = 'this_week';

    public const THIS_MONTH = 'this_month';

    public const ALL_TIME = 'all_time';

    private const LABELS = [
        self::TODAY => 'Hôm nay',
        self::THIS_WEEK => 'Tuần này',
        self::THIS_MONTH => 'Tháng này',
        self::ALL_TIME => 'Toàn thời gian',
    ];

    public static function normalize(mixed $period, string $default = self::THIS_WEEK): string
    {
        return is_string($period) && isset(self::LABELS[$period]) ? $period : $default;
    }

    public static function label(string $period): string
    {
        return self::LABELS[$period];
    }

    /**
     * Khoảng nửa mở theo giờ Việt Nam: [00:00 ngày đầu, 00:00 ngày sau ngày cuối].
     *
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    public static function bounds(string $period): array
    {
        if ($period === self::ALL_TIME) {
            return [null, null];
        }

        $today = Carbon::today(config('app.timezone'));
        $from = match ($period) {
            self::TODAY => $today->copy(),
            self::THIS_WEEK => $today->copy()->startOfWeek(Carbon::MONDAY),
            self::THIS_MONTH => $today->copy()->startOfMonth(),
        };

        return [$from, $today->copy()->addDay()];
    }

    public static function description(string $period): string
    {
        [$from, $until] = self::bounds($period);
        $description = $from
            ? $from->format('d/m/Y').' – '.$until->copy()->subDay()->format('d/m/Y')
            : 'Không giới hạn thời gian';

        if ($period === self::THIS_WEEK) {
            $description .= ' · Tuần bắt đầu từ thứ Hai';
        }

        return $description.' · Giờ Việt Nam (UTC+7)';
    }
}
