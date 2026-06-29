<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExtraServiceSetting;
use App\Models\Order;
use App\Models\OrderExtraService;
use Illuminate\Http\Request;

class OrderExtraServiceController extends Controller
{
    public function index(Order $order)
    {
        $services   = $order->extraServices()->with('creator')->get();
        $settings   = ExtraServiceSetting::first();
        $total      = $services->sum('total_amount');

        return response()->json([
            'services' => $services->map(fn($s) => [
                'id'           => $s->id,
                'service_type' => $s->service_type,
                'label'        => OrderExtraService::SERVICE_TYPES[$s->service_type] ?? $s->service_type,
                'icon'         => OrderExtraService::SERVICE_ICONS[$s->service_type] ?? 'bi-plus-circle',
                'description'  => $s->description,
                'quantity'     => $s->quantity,
                'unit_price'   => $s->unit_price,
                'total_amount' => $s->total_amount,
                'created_by'   => $s->creator?->name,
                'created_at'   => $s->created_at?->format('Y-m-d H:i'),
            ]),
            'total'    => $total,
            'settings' => $settings,
        ]);
    }

    public function store(Request $request, Order $order)
    {
        $data = $request->validate([
            'service_type' => ['required', 'in:overweight,packaging,storage_normal,storage_cold,delivery_attempt,other'],
            'description'  => ['nullable', 'string', 'max:255'],
            'quantity'     => ['required', 'numeric', 'min:0.01'],
            'unit_price'   => ['required', 'numeric', 'min:0'],
        ]);

        $total = round($data['quantity'] * $data['unit_price'], 2);

        $service = OrderExtraService::create([
            'order_id'     => $order->id,
            'service_type' => $data['service_type'],
            'description'  => $data['description'],
            'quantity'     => $data['quantity'],
            'unit_price'   => $data['unit_price'],
            'total_amount' => $total,
            'created_by'   => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تمت إضافة الخدمة الإضافية بنجاح',
            'service' => [
                'id'           => $service->id,
                'service_type' => $service->service_type,
                'label'        => OrderExtraService::SERVICE_TYPES[$service->service_type] ?? $service->service_type,
                'icon'         => OrderExtraService::SERVICE_ICONS[$service->service_type] ?? 'bi-plus-circle',
                'description'  => $service->description,
                'quantity'     => $service->quantity,
                'unit_price'   => $service->unit_price,
                'total_amount' => $service->total_amount,
                'created_at'   => $service->created_at->format('Y-m-d H:i'),
            ],
            'new_total' => $order->extraServices()->sum('total_amount'),
        ]);
    }

    public function destroy(Order $order, OrderExtraService $service)
    {
        abort_if($service->order_id !== $order->id, 403);
        $service->delete();

        return response()->json([
            'success'   => true,
            'message'   => 'تم حذف الخدمة',
            'new_total' => $order->extraServices()->sum('total_amount'),
        ]);
    }
}
