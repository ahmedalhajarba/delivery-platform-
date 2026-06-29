<?php

namespace App\Services\CallCenter;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IctcrmClient
{
    public function isEnabled(): bool
    {
        return (bool) config('ictcrm.enabled') && (string) config('ictcrm.base_url') !== '';
    }

    public function dial(string $extension, string $phone): array
    {
        return $this->request('post', (string) config('ictcrm.endpoints.dial'), [
            'extension' => $extension,
            'phone' => $phone,
        ]);
    }

    public function transfer(string $callId, string $toExtension): array
    {
        return $this->request('post', (string) config('ictcrm.endpoints.transfer'), [
            'call_id' => $callId,
            'to_extension' => $toExtension,
        ]);
    }

    public function request(string $method, string $endpoint, array $payload = []): array
    {
        if (!$this->isEnabled()) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'ICTCRM integration is disabled or not configured.',
                'data' => null,
            ];
        }

        $baseUrl = rtrim((string) config('ictcrm.base_url'), '/');
        $token = trim((string) config('ictcrm.api_key'));
        $timeout = (int) config('ictcrm.timeout', 15);

        try {
            $request = Http::timeout($timeout)
                ->acceptJson()
                ->asJson();

            if ($token !== '') {
                $request = $request
                    ->withToken($token)
                    ->withHeaders(['X-API-KEY' => $token]);
            }

            $response = $request->{$method}($baseUrl . '/' . ltrim($endpoint, '/'), $payload);

            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful() ? 'ok' : 'ICTCRM request failed.',
                'data' => $response->json(),
            ];
        } catch (\Throwable $e) {
            Log::warning('ICTCRM API request failed', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'status' => 500,
                'message' => 'ICTCRM request error: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }
}
