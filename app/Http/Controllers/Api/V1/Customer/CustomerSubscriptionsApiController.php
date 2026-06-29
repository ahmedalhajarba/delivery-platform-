<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\UserSubscription;
use Illuminate\Http\Request;

class CustomerSubscriptionsApiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->attributes->get('integrationUser');

        $subscriptions = UserSubscription::query()
            ->where('user_id', $user->id)
            ->with(['subscription:id,title_ar,title_en,orders_count', 'subscription.features'])
            ->latest('id')
            ->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'message' => 'ok',
            'data' => $subscriptions->items(),
            'meta' => [
                'current_page' => $subscriptions->currentPage(),
                'last_page' => $subscriptions->lastPage(),
                'per_page' => $subscriptions->perPage(),
                'total' => $subscriptions->total(),
            ],
        ]);
    }

    public function show(Request $request, UserSubscription $subscription)
    {
        $user = $request->attributes->get('integrationUser');

        abort_if((int) $subscription->user_id !== (int) $user->id, 403);

        $subscription->load(['subscription', 'subscription.features', 'renewals', 'extraCharges']);

        return response()->json([
            'message' => 'ok',
            'data' => $subscription,
        ]);
    }
}
