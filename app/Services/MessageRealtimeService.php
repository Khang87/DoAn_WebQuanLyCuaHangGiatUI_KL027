<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class MessageRealtimeService
{
    public function configuration(string $scope): array
    {
        $url = config('services.supabase.project_url');
        $key = config('services.supabase.publishable_key') ?: config('services.supabase.anon_key');
        // Never expose secret/service-role credentials through this endpoint.
        $legacy = is_string($key) ? json_decode(base64_decode(strtr(explode('.', $key)[1] ?? '', '-_', '+/')), true) : null;
        if (! is_string($url) || ! preg_match('~^https://[a-z0-9-]+\.supabase\.co/?$~', $url)
            || ! is_string($key) || (! str_starts_with($key, 'sb_publishable_') && ($legacy['role'] ?? null) !== 'anon')) {
            throw new RuntimeException('Realtime public configuration unavailable');
        }

        $now = now()->timestamp;
        $slot = intdiv($now + 30, 600);
        $expires = ($slot + 1) * 600;
        // Topics have immutable expiry: a previously disclosed capability cannot be prolonged.
        $row = DB::selectOne('INSERT INTO private.web_message_channels (scope, slot, topic, expires_at)
            VALUES (?, ?, ?, to_timestamp(?)) ON CONFLICT (scope, slot) DO UPDATE SET scope = EXCLUDED.scope RETURNING topic',
            [$scope, $slot, 'web-message:'.Str::uuid(), $expires]);
        DB::statement('DELETE FROM private.web_message_channels WHERE (scope, slot) IN
            (SELECT scope, slot FROM private.web_message_channels WHERE expires_at < now() ORDER BY expires_at LIMIT 100)');

        return ['url' => rtrim($url, '/'), 'key' => $key, 'topic' => $row->topic, 'expires_at' => $expires, 'expires_in' => $expires - $now];
    }
}
