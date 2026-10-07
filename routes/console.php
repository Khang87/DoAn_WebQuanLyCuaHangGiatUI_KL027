<?php

use App\Models\ThongBao;
use App\Services\DefaultNotificationPermission;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('notifications:grant-default', function () {
    $count = app(DefaultNotificationPermission::class)->backfill();
    $this->info("Đã gán quyền xem thông báo mặc định cho {$count} nhóm (không đổi schema).");
})->purpose('Backfill the mandatory personal inbox permission for all roles');

Schedule::call(function () {
    ThongBao::withoutGlobalScopes()
        ->where('LoaiThongBao', 'internal_password_otp')
        ->where('ThoiGianGui', '<=', now()->subMinutes(10))
        ->delete();
})->everyMinute()->name('prune-internal-otp-notifications');
