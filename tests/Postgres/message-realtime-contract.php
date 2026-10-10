<?php

use App\Services\MessageRealtimeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

DB::beginTransaction();
try {
    config(['services.supabase.project_url' => 'https://example.supabase.co',
        'services.supabase.publishable_key' => 'sb_publishable_test']);
    Carbon::setTestNow(Carbon::createFromTimestamp(2000000000));
    $service = app(MessageRealtimeService::class);
    $first = $service->configuration('order:1');
    $again = $service->configuration('order:1');
    $other = $service->configuration('order:2');
    pgExpect($first, $again, 'Same scope/time slot shares an immutable topic and expiry');
    pgExpect($first['topic'] !== $other['topic'], true, 'Different conversations have different opaque topics');
    pgExpect(str_contains($first['topic'], 'order:1'), false, 'Topic discloses no order identifier');
    $expiry = DB::selectOne('SELECT extract(epoch FROM expires_at)::bigint AS expiry FROM private.web_message_channels WHERE topic = ?', [$first['topic']])->expiry;
    pgExpect((int) $expiry, $first['expires_at'], 'Database and browser agree on absolute expiry');
    Carbon::setTestNow(Carbon::createFromTimestamp($first['expires_at'] - 20));
    $rotated = $service->configuration('order:1');
    pgExpect($rotated['topic'] !== $first['topic'], true, 'Renewal creates a fresh capability before expiry');
    pgExpect((int) DB::selectOne('SELECT extract(epoch FROM expires_at)::bigint AS expiry FROM private.web_message_channels WHERE topic = ?', [$first['topic']])->expiry,
        $first['expires_at'], 'Renewal never extends a disclosed topic');
    foreach (['sb_secret_forbidden', 'eyJhbGciOiJIUzI1NiJ9.'.rtrim(strtr(base64_encode('{"role":"service_role"}'), '+/', '-_'), '=').'.signature'] as $secret) {
        config(['services.supabase.publishable_key' => $secret]);
        $rejected = false;
        try {
            $service->configuration('order:1');
        } catch (RuntimeException) {
            $rejected = true;
        }
        pgExpect($rejected, true, 'Secret keys cannot be returned as browser configuration');
    }
    echo "PASS: Realtime registry service contracts\n";
} finally {
    Carbon::setTestNow();
    DB::rollBack();
}
