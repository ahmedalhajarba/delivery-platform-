<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderReturn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function show(string $waybill)
    {
        $order = $this->findOrderByWaybill($waybill);
        $orderReturn = $order?->orderReturn;

        if (!$orderReturn) {
            $orderReturn = $this->findOrderReturnByWaybill($waybill);
            if (!$order) {
                $order = $orderReturn?->originalOrder;
            }
        }

        return view('tracking.index', [
            'waybill' => $waybill,
            'order' => $order,
            'orderReturn' => $orderReturn,
            'orders' => collect(),
            'mode' => 'single',
        ]);
    }

    public function search(Request $request)
    {
        $data = $request->validate([
            'waybill' => ['required', 'string', 'max:100'],
        ]);

        return redirect()->route('tracking.show', ['waybill' => trim($data['waybill'])]);
    }

    public function multiSearch(Request $request)
    {
        $data = $request->validate([
            'waybills' => ['required', 'string'],
        ]);

        $waybills = collect(preg_split('/[\s,]+/', $data['waybills']))
            ->filter()
            ->map(static fn ($value) => trim($value))
            ->unique()
            ->values();

        $orders = Order::query()
            ->with(['order_status:id,name_ar,name_en', 'sender:id,name,mobile', 'recipient:id,name,mobile'])
            ->whereIn('reference_number', $waybills)
            ->orderByDesc('id')
            ->get();

        return view('tracking.index', [
            'waybill' => null,
            'order' => null,
            'orderReturn' => null,
            'orders' => $orders,
            'mode' => 'multi',
        ]);
    }

    public function searchByMobile(Request $request)
    {
        $data = $request->validate([
            'mobile' => ['required', 'string', 'max:30'],
        ]);

        $mobile = trim($data['mobile']);

        $orders = Order::query()
            ->with(['order_status:id,name_ar,name_en', 'sender:id,name,mobile', 'recipient:id,name,mobile'])
            ->whereHas('sender', function ($query) use ($mobile) {
                $query->where('mobile', $mobile)->orWhere('s_mobile', $mobile);
            })
            ->orWhereHas('recipient', function ($query) use ($mobile) {
                $query->where('mobile', $mobile)->orWhere('s_mobile', $mobile);
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('tracking.index', [
            'waybill' => null,
            'order' => null,
            'orderReturn' => null,
            'orders' => $orders,
            'mode' => 'mobile',
        ]);
    }

    public function api(Request $request, ?string $waybill = null): JsonResponse
    {
        $incoming = $waybill ?? $request->input('waybill');
        $waybills = is_array($incoming) ? $incoming : [$incoming];

        $waybills = collect($waybills)
            ->filter()
            ->map(static fn ($value) => trim((string) $value))
            ->unique()
            ->values();

        if ($waybills->isEmpty()) {
            return response()->json([
                'message' => 'waybill is required',
                'data' => [],
            ], 422);
        }

        $orders = Order::query()
            ->with(['order_status:id,name_ar,name_en', 'sender:id,name,mobile', 'recipient:id,name,mobile'])
            ->whereIn('reference_number', $waybills)
            ->orWhereIn('id', $waybills->filter(static fn ($v) => is_numeric($v))->all())
            ->get()
            ->map(function (Order $order) {
                return [
                    'id' => $order->id,
                    'waybill' => $order->reference_number,
                    'status_id' => $order->order_status_id,
                    'status_ar' => optional($order->order_status)->name_ar,
                    'status_en' => optional($order->order_status)->name_en,
                    'sender_mobile' => optional($order->sender)->mobile,
                    'recipient_mobile' => optional($order->recipient)->mobile,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                ];
            })
            ->values();

        return response()->json([
            'message' => 'ok',
            'count' => $orders->count(),
            'data' => $orders,
        ]);
    }

    private function findOrderByWaybill(string $waybill): ?Order
    {
        $query = Order::query()
            ->with(['order_status:id,name_ar,name_en', 'sender:id,name,mobile', 'recipient:id,name,mobile', 'orderReturn'])
            ->where(function ($q) use ($waybill) {
                $q->where('reference_number', $waybill)
                  ->orWhere('waybill_number', $waybill);
            });

        if (is_numeric($waybill)) {
            $query->orWhere('id', (int) $waybill);
        }

        return $query->orderByDesc('id')->first();
    }

    private function findOrderReturnByWaybill(string $waybill): ?OrderReturn
    {
        $normalizedWaybill = strtoupper(trim($waybill));

        return OrderReturn::query()
            ->with([
                'originalOrder.order_status:id,name_ar,name_en',
                'originalOrder.sender:id,name,mobile',
                'originalOrder.recipient:id,name,mobile',
            ])
            ->whereRaw('UPPER(return_waybill_number) = ?', [$normalizedWaybill])
            ->latest('id')
            ->first();
    }
}
