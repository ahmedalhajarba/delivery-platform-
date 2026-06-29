<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerOrdersApiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->attributes->get('integrationUser');

        $orders = Order::query()
            ->where('user_id', $user->id)
            ->with(['order_status:id,name_ar,name_en'])
            ->latest('id')
            ->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'message' => 'ok',
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function store(Request $request, SubscriptionService $subscriptionService)
    {
        $user = $request->attributes->get('integrationUser');

        $validated = $request->validate([
            'sender_id' => ['nullable', 'integer', 'exists:addresses,id'],
            'recipient_id' => ['nullable', 'integer', 'exists:addresses,id'],
            'shipment_type' => ['required', 'string', 'max:30'],
            'package_type' => ['required', Rule::in(array_keys(Order::PACKAGE_TYPE))],
            'package_content' => ['nullable', 'string', 'max:500'],
            'packages_count' => ['nullable', 'integer', 'min:1'],
            'package_weight' => ['nullable', 'numeric', 'min:0'],
            'stated_value' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'order_type' => ['nullable', Rule::in(array_keys(Order::ORDER_TYPE))],
            'payment_status' => ['nullable', Rule::in(array_keys(Order::PAYMENT_STATUS))],
            'cod_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        foreach (['sender_id', 'recipient_id'] as $key) {
            if (!empty($validated[$key])) {
                $isOwned = Address::query()
                    ->where('id', (int) $validated[$key])
                    ->where('user_id', $user->id)
                    ->exists();

                if (!$isOwned) {
                    return response()->json([
                        'message' => 'The selected address does not belong to this customer.',
                        'field' => $key,
                    ], 422);
                }
            }
        }

        if (($validated['order_type'] ?? null) === 'subscription') {
            $eligibility = $subscriptionService->canCreateSubscriptionOrder((int) $user->id);
            if (empty($eligibility['allowed'])) {
                return response()->json([
                    'message' => (string) ($eligibility['message'] ?? 'Subscription order is not allowed.'),
                ], 422);
            }
        }

        $order = Order::query()->create([
            'user_id' => $user->id,
            'sender_id' => $validated['sender_id'] ?? null,
            'recipient_id' => $validated['recipient_id'] ?? null,
            'shipment_type' => (string) $validated['shipment_type'],
            'package_type' => (string) $validated['package_type'],
            'package_content' => $validated['package_content'] ?? null,
            'packages_count' => $validated['packages_count'] ?? 1,
            'package_weight' => $validated['package_weight'] ?? null,
            'stated_value' => $validated['stated_value'] ?? null,
            'reference_number' => $validated['reference_number'] ?? null,
            'note' => $validated['note'] ?? null,
            'order_type' => $validated['order_type'] ?? 'single',
            'payment_status' => $validated['payment_status'] ?? 'pending',
            'final_shopping_cost' => 0,
            'cod_amount' => $validated['cod_amount'] ?? 0,
        ]);

        $order->load('order_status:id,name_ar,name_en');

        return response()->json([
            'message' => 'Order created successfully.',
            'data' => $order,
        ], 201);
    }
}
