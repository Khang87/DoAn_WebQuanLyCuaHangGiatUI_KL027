<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$app = require __DIR__.'/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
$cases = json_decode(file_get_contents(getenv('WEB_E2E_RUNTIME').'/cases.json'), true, flags: JSON_THROW_ON_ERROR);
if (($argv[1] ?? '') !== 'insert') {
    throw new RuntimeException('Expected isolated message fixture insertion');
}
foreach (['paid' => '<img src=x onerror="window.messageXss=true"> Mobile message', 'legacy' => 'Foreign conversation message'] as $key => $content) {
    DB::table('TinNhan')->insert(['NguoiGuiID' => 1, 'NguoiNhanID' => 2, 'DonHangID' => $cases[$key], 'NoiDung' => $content, 'ThoiGianGui' => now(), 'TrangThai' => 'Đã gửi']);
}
echo "Fixture messages inserted\n";
