<?php

namespace App\Services\Notifications;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FirebaseCloudMessaging
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function configured(): bool
    {
        $credentials = config('services.firebase.credentials');

        return filled(config('services.firebase.project_id'))
            && ((is_string($credentials) && is_readable($credentials))
                || filled(config('services.firebase.credentials_json')));
    }

    /**
     * @return bool false when Firebase says the device token is no longer valid
     */
    public function send(string $deviceToken, array $message): bool
    {
        if (! $this->configured()) {
            return true;
        }

        $projectId = (string) config('services.firebase.project_id');
        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->timeout(15)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => ['token' => $deviceToken, ...$message],
            ]);

        if ($response->successful()) {
            return true;
        }

        $status = (string) $response->json('error.status');
        $details = collect($response->json('error.details', []));
        $fcmCode = $details->firstWhere('@type', 'type.googleapis.com/google.firebase.fcm.v1.FcmError')['errorCode'] ?? null;

        if ($status === 'NOT_FOUND' || in_array($fcmCode, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
            return false;
        }

        throw new RequestException($response);
    }

    private function accessToken(): string
    {
        $credentialsPath = (string) config('services.firebase.credentials');
        $encodedCredentials = (string) config('services.firebase.credentials_json');
        $cacheKey = 'firebase:messaging-access-token:'.hash('sha256', $credentialsPath.$encodedCredentials);

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($credentialsPath, $encodedCredentials): string {
            $contents = $encodedCredentials !== ''
                ? base64_decode($encodedCredentials, true)
                : file_get_contents($credentialsPath);
            if (! is_string($contents)) {
                throw new RuntimeException('Firebase credentials could not be read.');
            }
            $json = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
            $credentials = new ServiceAccountCredentials([self::SCOPE], $json);
            $token = $credentials->fetchAuthToken()['access_token'] ?? null;

            if (! is_string($token) || $token === '') {
                throw new RuntimeException('Firebase did not issue an access token.');
            }

            return $token;
        });
    }
}
