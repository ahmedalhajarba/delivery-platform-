<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAttempt;
use App\Models\ExtraServiceSetting;
use App\Models\Order;
use App\Models\OrderExtraService;
use Illuminate\Http\Request;

class DeliveryAttemptController extends Controller
{
    public function index(Order $order)
    {
        $attempts = $order->deliveryAttempts()->with('courier')->get();
        $settings = ExtraServiceSetting::first();

        return response()->json([
            'attempts' => $attempts->map(fn($a) => [
                'id'             => $a->id,
                'attempt_number' => $a->attempt_number,
                'result'         => $a->result,
                'result_label'   => DeliveryAttempt::RESULTS[$a->result] ?? $a->result,
                'result_color'   => DeliveryAttempt::RESULT_COLORS[$a->result] ?? 'secondary',
                'notes'          => $a->notes,
                'attempt_cost'   => $a->attempt_cost,
                'courier'        => $a->courier?->name,
                'attempted_at'   => $a->attempted_at?->format('Y-m-d H:i'),
            ]),
            'free_attempts' => $settings?->delivery_free_attempts ?? 1,
            'attempt_cost'  => $settings?->delivery_attempt_cost ?? 0,
            'total_attempts'=> $attempts->count(),
        ]);
    }

    public function store(Request $request, Order $order)
    {
        $data = $request->validate([
            'result'       => ['required', 'in:' . implode(',', array_keys(DeliveryAttempt::RESULTS))],
            'notes'        => ['nullable', 'string', 'max:500'],
            'attempted_at' => ['nullable', 'date'],
        ]);

        $settings       = ExtraServiceSetting::first();
        $freeAttempts   = $settings?->delivery_free_attempts ?? 1;
        $attemptCost    = $settings?->delivery_attempt_cost ?? 0;
        $attemptCount   = $order->deliveryAttempts()->count() + 1;

        // احتساب التكلفة إذا تجاوزت المجاني
        $cost = ($settings?->delivery_attempt_enabled && $attemptCount > $freeAttempts)
            ? $attemptCost
            : 0;

        $attempt = DeliveryAttempt::create([
            'order_id'       => $order->id,
            'attempt_number' => $attemptCount,
            'result'         => $data['result'],
            'notes'          => $data['notes'],
            'attempt_cost'   => $cost,
            'courier_id'     => $order->assigned_courier_id,
            'attempted_at'   => $data['attempted_at'] ?? now(),
        ]);

        // إضافة خدمة محاولة توصيل إضافية تلقائياً إذا كان هناك تكلفة
        if ($cost > 0) {
            OrderExtraService::create([
                'order_id'     => $order->id,
                'service_type' => 'delivery_attempt',
                'description'  => 'محاولة توصيل رقم ' . $attemptCount,
                'quantity'     => 1,
                'unit_price'   => $cost,
                'total_amount' => $cost,
                'created_by'   => auth()->id(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل محاولة التوصيل',
            'attempt' => [
                'id'             => $attempt->id,
                'attempt_number' => $attempt->attempt_number,
                'result'         => $attempt->result,
                'result_label'   => DeliveryAttempt::RESULTS[$attempt->result] ?? $attempt->result,
                'result_color'   => DeliveryAttempt::RESULT_COLORS[$attempt->result] ?? 'secondary',
                'notes'          => $attempt->notes,
                'attempt_cost'   => $attempt->attempt_cost,
                'attempted_at'   => $attempt->attempted_at?->format('Y-m-d H:i'),
            ],
        ]);
    }
}
