<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CallCenter\CallerLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IctcrmWebhookController extends Controller
{
    private $callerLookup;

    public function __construct(CallerLookupService $callerLookup)
    {
        $this->callerLookup = $callerLookup;
    }

    public function incomingCall(Request $request): JsonResponse
    {
        $configuredToken = trim((string) config('ictcrm.webhook_token'));
        $requireToken = (bool) config('ictcrm.require_webhook_token', true);
        $incomingToken = trim((string) ($request->header('X-ICTCRM-TOKEN') ?: $request->input('token')));

        if ($requireToken && $configuredToken === '') {
            return response()->json([
                'ok' => false,
                'message' => 'ICTCRM webhook token is required but not configured.',
            ], 503);
        }

        if ($configuredToken !== '' && !hash_equals($configuredToken, $incomingToken)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid webhook token.',
            ], 401);
        }

        $data = $request->validate([
            'phone' => 'nullable|string|max:30',
            'caller_phone' => 'nullable|string|max:30',
            'call_id' => 'nullable|string|max:100',
            'direction' => 'nullable|in:inbound,outbound',
        ]);

        $request->merge([
            'phone' => $data['phone'] ?? ($data['caller_phone'] ?? null),
            'call_id' => $data['call_id'] ?? null,
            'direction' => $data['direction'] ?? 'inbound',
        ]);

        $customer = $this->callerLookup->findCustomerByPhone($request->input('phone'));
        $context = $this->callerLookup->buildScreenPopContext($request, $customer);

        return response()->json([
            'ok' => true,
            'message' => 'Screen pop context generated.',
        ] + $context);
    }
}
