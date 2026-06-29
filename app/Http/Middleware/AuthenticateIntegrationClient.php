<?php

namespace App\Http\Middleware;

use App\Models\IntegrationApiClient;
use Closure;
use Illuminate\Http\Request;

class AuthenticateIntegrationClient
{
    public function handle(Request $request, Closure $next)
    {
        $rawKey = trim((string) $request->header('X-CLIENT-KEY', ''));

        if ($rawKey === '') {
            return response()->json([
                'message' => 'Missing X-CLIENT-KEY header.',
            ], 401);
        }

        $client = IntegrationApiClient::query()
            ->with('user')
            ->where('key_hash', hash('sha256', $rawKey))
            ->where('status', 'active')
            ->first();

        if (!$client) {
            return response()->json([
                'message' => 'Invalid or inactive API client key.',
            ], 401);
        }

        if (!$client->user) {
            return response()->json([
                'message' => 'API client is not linked to a customer account.',
            ], 403);
        }

        $request->attributes->set('integrationClient', $client);
        $request->attributes->set('integrationUser', $client->user);
        $request->setUserResolver(function () use ($client) {
            return $client->user;
        });

        $client->forceFill([
            'last_used_at' => now(),
            'last_ip' => $request->ip(),
        ])->save();

        return $next($request);
    }
}
