<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class CustomerTrackingApiController extends Controller
{
    public function show(Request $request, string $waybill)
    {
        $user = $request->attributes->get('integrationUser');
        $order = $this->findCustomerOrder($user->id, $waybill);

        if (!$order) {
            return response()->json([
                'message' => 'Waybill not found for this customer.',
            ], 404);
        }

        return response()->json([
            'message' => 'ok',
            'data' => $this->transformOrderTracking($order),
        ]);
    }

    public function batch(Request $request)
    {
        $user = $request->attributes->get('integrationUser');

        $validated = $request->validate([
            'waybills' => ['required', 'array', 'min:1', 'max:20'],
            'waybills.*' => ['required', 'string', 'max:100'],
        ]);

        $results = collect($validated['waybills'])
            ->mapWithKeys(function ($waybill) use ($user) {
                $order = $this->findCustomerOrder($user->id, (string) $waybill);

                if (!$order) {
                    return [
                        (string) $waybill => [
                            'found' => false,
                            'message' => 'Waybill not found for this customer.',
                        ],
                    ];
                }

                return [
                    (string) $waybill => [
                        'found' => true,
                        'tracking' => $this->transformOrderTracking($order),
                    ],
                ];
            });

        return response()->json([
            'message' => 'ok',
            'count' => $results->count(),
            'data' => $results,
        ]);
    }

    private function findCustomerOrder(int $userId, string $waybill): ?Order
    {
        $waybill = trim($waybill);

        return Order::query()
            ->where('user_id', $userId)
            ->where(function ($q) use ($waybill) {
                $q->where('reference_number', $waybill)
                    ->orWhere('waybill_number', $waybill);

                if (is_numeric($waybill)) {
                    $q->orWhere('id', (int) $waybill);
                }
            })
            ->with([
                'order_status:id,name_ar,name_en',
                'latestTracking.trackingStatus',
                'shipmentTracking' => function ($q) {
                    $q->with('trackingStatus')->orderBy('event_time');
                },
            ])
            ->latest('id')
            ->first();
    }

    private function transformOrderTracking(Order $order): array
    {
        return [
            'order_id' => $order->id,
            'waybill' => $order->waybill_number ?: $order->reference_number,
            'reference_number' => $order->reference_number,
            'status' => [
                'id' => $order->order_status_id,
                'name_ar' => $order->order_status?->name_ar,
                'name_en' => $order->order_status?->name_en,
            ],
            'latest_tracking' => [
                'status_code' => $order->latestTracking?->trackingStatus?->code,
                'status_ar' => $order->latestTracking?->trackingStatus?->name_ar,
                'status_en' => $order->latestTracking?->trackingStatus?->name_en,
                'is_final' => (bool) $order->latestTracking?->trackingStatus?->is_final,
                'updated_at' => $order->latestTracking?->event_time,
                'notes' => $order->latestTracking?->notes,
            ],
            'timeline' => $order->shipmentTracking->map(function ($item) {
                return [
                    'status_code' => $item->trackingStatus?->code,
                    'status_ar' => $item->trackingStatus?->name_ar,
                    'status_en' => $item->trackingStatus?->name_en,
                    'time' => $item->event_time,
                    'location' => $item->location_description,
                    'notes' => $item->notes,
                ];
            })->values(),
        ];
    }
}
