<?php

namespace App\Services;

use App\Exceptions\SupabasePasswordRecoveryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupabasePasswordRecoveryService
{
    public function send(string $email): void
    {
        $projectUrl = rtrim((string) config('services.supabase.project_url'), '/');
        $apiKey = (string) (config('services.supabase.anon_key') ?: config('services.supabase.publishable_key'));

        if ($projectUrl === '' || $apiKey === '') {
            Log::error('Supabase password recovery is not configured.');

            throw new SupabasePasswordRecoveryException;
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['apikey' => $apiKey])
                ->withToken($apiKey)
                ->timeout(10)
                ->post($projectUrl.'/auth/v1/recover', ['email' => $email]);
        } catch (ConnectionException $exception) {
            Log::error('Supabase password recovery request could not be completed.', [
                'exception' => $exception::class,
            ]);

            throw new SupabasePasswordRecoveryException(previous: $exception);
        }

        if (! $response->successful()) {
            Log::warning('Supabase rejected a password recovery request.', [
                'status' => $response->status(),
            ]);

            throw new SupabasePasswordRecoveryException;
        }
    }
}
