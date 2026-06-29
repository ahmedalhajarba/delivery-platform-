<?php

namespace App\Http\Middleware;

use App\Models\IntegrationApiLog;
use Closure;
use Illuminate\Http\Request;

class LogIntegrationApiRequest
{
    public function handle(Request $request, Closure $next)
    {
        $startedAt = microtime(true);
        $response = $next($request);

        try {
            $client = $request->attributes->get('integrationClient');
            $user = $request->attributes->get('integrationUser');

            $payload = $request->except([
                'password',
                'token',
                'api_key',
                'bank_iban',
                'bank_account_number',
            ]);

            $responseContent = method_exists($response, 'getContent') ? (string) $response->getContent() : '';
            if (strlen($responseContent) > 4000) {
                $responseContent = substr($responseContent, 0, 4000) . '...';
            }

            IntegrationApiLog::query()->create([
                'client_id' => $client?->id,
                'user_id' => $user?->id,
                'request_method' => $request->method(),
                'request_path' => $request->path(),
                'request_payload' => empty($payload) ? null : $payload,
                'response_status' => method_exists($response, 'status') ? (int) $response->status() : null,
                'response_body' => $responseContent !== '' ? $responseContent : null,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'ip_address' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            // Logging should never interrupt API flow.
        }

        return $response;
    }
}
