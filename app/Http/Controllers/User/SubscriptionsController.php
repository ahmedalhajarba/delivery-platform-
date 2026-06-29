<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\UserSubscription;
use App\Services\SubscriptionService;

class SubscriptionsController extends Controller
{
    public function __construct(protected SubscriptionService $service)
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $subscriptions = UserSubscription::where('user_id', auth()->id())
                                         ->with(['subscription', 'subscription.features'])
                                         ->latest()
                                         ->paginate(15);

        return view('user.subscriptions.index', compact('subscriptions'));
    }

    public function show(UserSubscription $subscription)
    {
        abort_if($subscription->user_id !== auth()->id(), 403);
        $subscription->load(['subscription', 'subscription.features', 'renewals', 'extraCharges']);
        return view('user.subscriptions.show', compact('subscription'));
    }

    public function invoice(UserSubscription $subscription)
    {
        abort_if($subscription->user_id !== auth()->id(), 403);

        $subscription->load(['subscription', 'subscription.features']);

        $pricing = [
            'shipments_price_total' => (float) ($subscription->shipments_price_total ?? 0),
            'paid_services_price_total' => (float) ($subscription->paid_services_price_total ?? 0),
            'subtotal_before_tax' => (float) ($subscription->subtotal_before_tax ?? 0),
            'tax_enabled' => (bool) ($subscription->tax_enabled ?? false),
            'tax_type' => $subscription->tax_type,
            'tax_rate' => (float) ($subscription->tax_rate ?? 0),
            'tax_amount' => (float) ($subscription->tax_amount ?? 0),
            'total_price' => (float) ($subscription->total_price ?? $subscription->paid_amount ?? 0),
        ];

        if ($pricing['subtotal_before_tax'] <= 0 && $subscription->subscription) {
            $pricing = $this->service->calculatePlanPricing($subscription->subscription);
            $pricing['total_price'] = (float) ($subscription->paid_amount ?? $pricing['total_price']);
        }

        return view('user.subscriptions.invoice', compact('subscription', 'pricing'));
    }
}
