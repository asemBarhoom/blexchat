<?php

namespace App\Services\Channels;

/**
 * Reads the `signed_request` Meta posts to the deauthorize and data deletion callbacks:
 * base64url(signature) "." base64url(json payload), signed HMAC-SHA256 with the app secret.
 * The Instagram app and the Meta app (Messenger, WhatsApp) have their own secrets; either may sign.
 */
class MetaSignedRequest
{
    /**
     * @return array<string, mixed>|null The payload, or null when the request is malformed or not signed by us.
     */
    public function parse(?string $signedRequest): ?array
    {
        if (! is_string($signedRequest) || substr_count($signedRequest, '.') !== 1) {
            return null;
        }

        [$encodedSignature, $encodedPayload] = explode('.', $signedRequest);
        $signature = self::decode($encodedSignature);
        $payload = json_decode(self::decode($encodedPayload), true);

        if (! is_array($payload) || ($payload['algorithm'] ?? null) !== 'HMAC-SHA256' || ! isset($payload['user_id'])) {
            return null;
        }

        foreach ($this->secrets() as $secret) {
            if (hash_equals(hash_hmac('sha256', $encodedPayload, $secret, true), $signature)) {
                return $payload;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function secrets(): array
    {
        return array_values(array_unique(array_filter([
            config('services.instagram.app_secret'),
            config('services.meta.app_secret'),
        ])));
    }

    private static function decode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
