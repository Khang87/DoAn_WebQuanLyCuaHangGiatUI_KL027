<?php

namespace App\Services;

use App\Models\TinNhan;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class ChatMessageDecryptionService
{
    private ?string $key = null;

    /**
     * @param  Collection<int, TinNhan>  $messages
     * @return Collection<int, TinNhan>
     */
    public function decryptMessages(Collection $messages): Collection
    {
        foreach ($messages as $message) {
            $message->setAttribute(
                'NoiDung',
                $this->decryptContent(
                    (string) $message->getAttribute('NoiDung'),
                    $message->getAttribute('NoiDungMaHoa'),
                    $message->getAttribute('NoiDungNonce'),
                    $message->getAttribute('MaHoaPhienBan'),
                ),
            );
        }

        return $messages;
    }

    public function decryptContent(
        string $plainText,
        mixed $cipherText,
        mixed $nonce,
        mixed $version,
    ): string {
        if ($cipherText === null) {
            return $plainText;
        }

        if (! is_string($cipherText) || ! is_string($nonce) || ! in_array($version, [1, '1'], true)) {
            throw new ChatMessageDecryptionException('Encrypted chat message metadata is invalid.');
        }

        $rawNonce = $this->decodeBase64($nonce);
        $rawCipherText = $this->decodeBase64($cipherText);

        if ($rawNonce === null || strlen($rawNonce) !== 12 || $rawCipherText === null || strlen($rawCipherText) < 16) {
            throw new ChatMessageDecryptionException('Encrypted chat message payload is invalid.');
        }

        $tag = substr($rawCipherText, -16);
        $encryptedContent = substr($rawCipherText, 0, -16);
        $plainText = openssl_decrypt(
            $encryptedContent,
            'aes-256-gcm',
            $this->encryptionKey(),
            OPENSSL_RAW_DATA,
            $rawNonce,
            $tag,
        );

        if (! is_string($plainText)) {
            throw new ChatMessageDecryptionException('Encrypted chat message could not be decrypted.');
        }

        return $plainText;
    }

    /** @return array{ciphertext: string, nonce: string} */
    public function encryptContent(string $plainText): array
    {
        $nonce = random_bytes(12);
        $tag = '';
        $cipherText = openssl_encrypt(
            $plainText,
            'aes-256-gcm',
            $this->encryptionKey(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            16,
        );

        if (! is_string($cipherText) || strlen($tag) !== 16) {
            throw new ChatMessageDecryptionException('Chat message could not be encrypted.');
        }

        return ['ciphertext' => base64_encode($cipherText.$tag), 'nonce' => base64_encode($nonce)];
    }

    private function encryptionKey(): string
    {
        if ($this->key !== null) {
            return $this->key;
        }

        $projectUrl = rtrim((string) config('services.supabase.project_url'), '/');
        $serviceRoleKey = config('services.supabase.service_role_key');

        if (! preg_match('~^https://[a-z0-9-]+\.supabase\.co$~', $projectUrl)
            || ! is_string($serviceRoleKey) || $serviceRoleKey === '') {
            throw new ChatMessageDecryptionException('Chat message decryption is not configured.');
        }

        try {
            $response = Http::withHeaders([
                'apikey' => $serviceRoleKey,
                'Authorization' => 'Bearer '.$serviceRoleKey,
            ])->acceptJson()->timeout(10)->post($projectUrl.'/rest/v1/rpc/get_chat_encryption_key');
        } catch (ConnectionException $exception) {
            throw new ChatMessageDecryptionException(
                'Chat message encryption key is temporarily unavailable.',
                previous: $exception,
            );
        }

        $encodedKey = $response->successful() ? $response->json() : null;

        if ($response->successful() && ! is_string($encodedKey)
            && str_starts_with((string) $response->header('Content-Type'), 'text/plain')) {
            $encodedKey = trim($response->body());
        }

        if (! is_string($encodedKey) || $encodedKey === '') {
            throw new ChatMessageDecryptionException('Chat message encryption key is unavailable.');
        }

        if (preg_match('/^[a-fA-F0-9]{64}$/D', $encodedKey) === 1) {
            $key = hex2bin($encodedKey);
        } else {
            $key = $this->decodeBase64($encodedKey);
        }

        if ((! is_string($key) || strlen($key) !== 32) && strlen($encodedKey) === 32) {
            $key = $encodedKey;
        }

        if (! is_string($key) || strlen($key) !== 32) {
            throw new ChatMessageDecryptionException('Chat message encryption key has an invalid format.');
        }

        return $this->key = $key;
    }

    private function decodeBase64(string $value): ?string
    {
        if ($value === '' || preg_match('~^[A-Za-z0-9+/_-]+={0,2}$~D', $value) !== 1) {
            return null;
        }

        $unpadded = rtrim($value, '=');
        $expectedPadding = (4 - strlen($unpadded) % 4) % 4;
        $providedPadding = strlen($value) - strlen($unpadded);

        if (strlen($unpadded) % 4 === 1 || ($providedPadding !== 0 && $providedPadding !== $expectedPadding)) {
            return null;
        }

        $normalized = strtr($unpadded, '-_', '+/').str_repeat('=', $expectedPadding);
        $decoded = base64_decode($normalized, true);

        return $decoded === false ? null : $decoded;
    }
}
