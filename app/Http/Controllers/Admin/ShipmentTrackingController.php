<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BranchEmployee;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\ShipmentAction;
use App\Models\ShipmentTracking;
use App\Models\TrackingStatus;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShipmentTrackingController extends Controller
{
    // ─── قائمة سجلات التتبع ─────────────────────────────────────────────────
    public function index(Request $request)
    {
        abort_if(Gate::denies('order_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $query = ShipmentTracking::with([
            'order', 'trackingStatus', 'branch', 'courier', 'recordedBy',
        ])->latest('event_time');
        $matchedOrderIds = collect();

        if ($request->filled('order_id')) {
            $val = trim((string) $request->order_id);
            $normalizedVal = strtoupper($val);
            $resolvedIds = collect();

            if ($val === '') {
                $query->whereRaw('1 = 0');
            } else {
                // 1) مطابقة دقيقة لبوليصة الرجيع RET-...
                if (str_starts_with($normalizedVal, 'RET-')) {
                    $returnMatch = OrderReturn::query()
                        ->whereRaw('UPPER(return_waybill_number) = ?', [$normalizedVal])
                        ->first(['original_order_id', 'return_order_id']);

                    $resolvedIds = $resolvedIds
                        ->merge([$returnMatch?->original_order_id, $returnMatch?->return_order_id]);
                }

                // 2) مطابقة دقيقة لبوليصة الطلب أو المرجع أو رقم الطلب
                $exactOrderIds = Order::query()
                    ->whereRaw('UPPER(waybill_number) = ?', [$normalizedVal])
                    ->orWhereRaw('UPPER(reference_number) = ?', [$normalizedVal]);

                if (is_numeric($val)) {
                    $exactOrderIds->orWhere('id', (int) $val);
                }

                $resolvedIds = $resolvedIds->merge($exactOrderIds->pluck('id'));

                $resolvedIds = $resolvedIds->filter()->unique()->values();
                $matchedOrderIds = $resolvedIds;

                if ($resolvedIds->isEmpty()) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereIn('order_id', $resolvedIds);
                }
            }
        }
        if ($request->filled('status_id')) {
            $query->where('tracking_status_id', $request->status_id);
        }
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('event_time', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('event_time', '<=', $request->date_to);
        }

        $records  = $query->paginate(30)->withQueryString();
        $statuses = TrackingStatus::orderBy('sort_order')->get();
        $branches = Branch::orderBy('title_ar')->get();
        $matchedOrderIds = $matchedOrderIds->values();

        return view('admin.shipment-tracking.index', compact('records', 'statuses', 'branches', 'matchedOrderIds'));
    }

    // ─── إضافة حدث تتبع لطلب ────────────────────────────────────────────────
    public function store(Request $request)
    {
        abort_if(Gate::denies('order_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'order_id'            => 'required|exists:orders,id',
            'tracking_status_id'  => 'required|exists:tracking_statuses,id',
            'branch_id'           => 'nullable|exists:branches,id',
            'courier_id'          => 'nullable|exists:branch_employees,id',
            'location_description'=> 'nullable|string|max:255',
            'latitude'            => 'nullable|numeric',
            'longitude'           => 'nullable|numeric',
            'notes'               => 'nullable|string|max:1000',
            'event_time'          => 'nullable|date',
            'updated_by_role'     => 'nullable|in:admin,courier,branch,carrier,system,customer',
            'recipient_name_actual' => 'nullable|string|max:255',
        ]);

        $data['recorded_by']     = auth()->id();
        $data['event_time']      = $data['event_time'] ?? now();
        $data['updated_by_role'] = $data['updated_by_role'] ?? 'admin';

        ShipmentTracking::create($data);

        // تحديث حالة الطلب
        Order::where('id', $data['order_id'])
            ->update(['order_status_id' => $data['tracking_status_id']]);

        return back()->with('message', 'تم تسجيل حدث التتبع بنجاح');
    }

    // ─── تفاصيل تتبع طلب واحد ───────────────────────────────────────────────
    public function show(Order $order)
    {
        abort_if(Gate::denies('order_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $order->load([
            'sender.city', 'sender.governorate',
            'recipient.city', 'recipient.governorate',
            'originBranch', 'destinationBranch',
            'orderReturn',
            'shipmentTracking' => fn($q) => $q->with(['trackingStatus', 'branch', 'courier', 'recordedBy', 'carrierCompany'])->orderBy('event_time'),
            'latestTracking.trackingStatus',
            'courierAssignments.courier.vehicle',
            'courierAssignments.assignedBy',
            'carrierWaybills.carrierCompany',
            'shipmentActions.performedBy',
            'deliverySchedules.courier',
        ]);

        $statuses  = TrackingStatus::where('is_active', true)->orderBy('sort_order')->get();
        $branches  = Branch::orderBy('title_ar')->get();
        $couriers  = BranchEmployee::with('branch')->orderBy('name')->get();

        return view('admin.shipment-tracking.show', compact('order', 'statuses', 'branches', 'couriers'));
    }

    // ─── تسجيل إجراء إداري ──────────────────────────────────────────────────
    public function storeAction(Request $request)
    {
        abort_if(Gate::denies('order_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'order_id'     => 'required|exists:orders,id',
            'action_type'  => 'required|string',
            'action_reason'=> 'required|string|max:500',
            'action_data'  => 'nullable|array',
        ]);

        $data['performed_by'] = auth()->id();
        $data['action_at']    = now();
        $data['status']       = 'executed';

        ShipmentAction::create($data);

        // تنفيذ الإجراء تلقائياً إذا كان "force_status"
        if ($data['action_type'] === 'force_status' && isset($data['action_data']['status_id'])) {
            ShipmentTracking::create([
                'order_id'           => $data['order_id'],
                'tracking_status_id' => $data['action_data']['status_id'],
                'recorded_by'        => auth()->id(),
                'notes'              => 'تغيير يدوي: ' . $data['action_reason'],
                'event_time'         => now(),
                'updated_by_role'    => 'admin',
            ]);
            Order::where('id', $data['order_id'])
                ->update(['order_status_id' => $data['action_data']['status_id']]);
        }

        return back()->with('message', 'تم تنفيذ الإجراء بنجاح');
    }

    // ─── API: تتبع بوليصة واحدة أو متعددة (حتى 10) ─────────────────────────
    public function apiTrack(Request $request)
    {
        $request->validate([
            'waybills'   => 'required|array|min:1|max:10',
            'waybills.*' => 'required|string|max:60',
        ]);

        $results = [];
        foreach ($request->waybills as $waybill) {
            $waybill = trim($waybill);
            $order   = Order::where('waybill_number', $waybill)
                ->orWhereHas('carrierWaybills', fn($q) => $q->where('carrier_waybill_number', $waybill))
                ->with([
                    'shipmentTracking.trackingStatus',
                    'latestTracking.trackingStatus',
                    'carrierWaybills.carrierCompany',
                ])
                ->first();

            if (!$order) {
                $results[$waybill] = ['found' => false, 'message' => 'رقم البوليصة غير موجود'];
                continue;
            }

            $results[$waybill] = [
                'found'          => true,
                'order_id'       => $order->id,
                'waybill_number' => $order->waybill_number,
                'carrier_waybill'=> $order->latestCarrierWaybill?->carrier_waybill_number,
                'carrier'        => $order->latestCarrierWaybill?->carrierCompany?->name_ar,
                'current_status' => $order->latestTracking?->trackingStatus?->name_ar,
                'current_status_code' => $order->latestTracking?->trackingStatus?->code,
                'status_color'   => $order->latestTracking?->trackingStatus?->color,
                'is_final'       => (bool) $order->latestTracking?->trackingStatus?->is_final,
                'last_update'    => $order->latestTracking?->event_time?->format('Y-m-d H:i'),
                'timeline'       => $order->shipmentTracking->map(fn($t) => [
                    'status'      => $t->trackingStatus?->name_ar,
                    'status_code' => $t->trackingStatus?->code,
                    'color'       => $t->trackingStatus?->color,
                    'location'    => $t->location_description,
                    'notes'       => $t->notes,
                    'by'          => $t->updated_by_role,
                    'time'        => $t->event_time?->format('Y-m-d H:i'),
                ]),
            ];
        }

        return response()->json($results);
    }

    // ─── حذف حدث تتبع ───────────────────────────────────────────────────────
    public function destroy(ShipmentTracking $shipmentTracking)
    {
        abort_if(Gate::denies('order_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        $shipmentTracking->delete();
        return back()->with('message', 'تم حذف حدث التتبع');
    }
}
