<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends a push notification through Firebase Cloud Messaging's HTTP v1 API.
 *
 * Authentication is a Google service-account key (a JSON file the
 * administrator downloads from the Firebase console). The signed token
 * request is built here with PHP's OpenSSL instead of adding a Google client
 * library, which would pull in a large dependency tree for one HTTP call.
 */
final class FirebaseCloudMessaging
{
    public const SENT = 'sent';

    /** The phone no longer has the app installed; its token should be deleted. */
    public const UNREGISTERED = 'unregistered';

    public const FAILED = 'failed';

    private const TOKEN_URI = 'https://oauth2.googleapis.com/token';

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const ACCESS_TOKEN_CACHE_KEY = 'fcm.access_token';

    public static function isConfigured(): bool
    {
        return self::credentials() !== null;
    }

    /**
     * @param  array<string, scalar>  $data  Delivered to the app alongside the visible text.
     * @return self::SENT|self::UNREGISTERED|self::FAILED
     */
    public static function send(string $deviceToken, string $title, string $body, array $data = []): string
    {
        $credentials = self::credentials();
        if ($credentials === null) {
            return self::FAILED;
        }

        try {
            $response = Http::withToken(self::accessToken($credentials))
                ->timeout(10)
                ->retry([1000, 5000], when: self::retryableFailure(...), throw: false)
                ->post("https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send", [
                    'message' => [
                        'token' => $deviceToken,
                        'notification' => ['title' => $title, 'body' => $body],
                        'data' => array_map('strval', $data),
                        'android' => ['priority' => 'HIGH'],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('Firebase push request failed.', ['exception' => $e]);

            return self::FAILED;
        }

        if ($response->successful()) {
            return self::SENT;
        }

        if ($response->status() === 404 || $response->json('error.status') === 'NOT_FOUND') {
            return self::UNREGISTERED;
        }

        // A rejected credential (revoked key, clock skew): drop the cached
        // access token so the next attempt signs a fresh one.
        if ($response->status() === 401) {
            Cache::forget(self::ACCESS_TOKEN_CACHE_KEY);
        }

        Log::warning('Firebase rejected a push notification.', [
            'status' => $response->status(),
            'error' => $response->json('error.status'),
        ]);

        return self::FAILED;
    }

    /** @return array{project_id: string, client_email: string, private_key: string}|null */
    private static function credentials(): ?array
    {
        $path = config('services.fcm.credentials_path');
        if (! $path || ! is_readable($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);
        if (! is_array($json)) {
            return null;
        }

        foreach (['project_id', 'client_email', 'private_key'] as $key) {
            if (empty($json[$key]) || ! is_string($json[$key])) {
                return null;
            }
        }

        return [
            'project_id' => $json['project_id'],
            'client_email' => $json['client_email'],
            'private_key' => $json['private_key'],
        ];
    }

    /** @param  array{project_id: string, client_email: string, private_key: string}  $credentials */
    private static function accessToken(array $credentials): string
    {
        // Google's access tokens last an hour; reuse one for 55 minutes.
        return Cache::remember(self::ACCESS_TOKEN_CACHE_KEY, 3300, function () use ($credentials): string {
            $now = time();
            $header = self::base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = self::base64Url(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::TOKEN_URI,
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            $signature = '';
            if (! openssl_sign("{$header}.{$claims}", $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('Could not sign the Firebase token request.');
            }

            $response = Http::asForm()->timeout(10)->post(self::TOKEN_URI, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => "{$header}.{$claims}.".self::base64Url($signature),
            ])->throw();

            return (string) $response->json('access_token');
        });
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /** Retry only connection failures, throttling, timeouts, and server errors. */
    private static function retryableFailure(\Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if (! $exception instanceof RequestException || ! $exception->response) {
            return false;
        }

        $status = $exception->response->status();

        return $status === 408 || $status === 429 || $status >= 500;
    }
}
