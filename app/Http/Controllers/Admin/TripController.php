<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Courier;
use App\Models\CourierAssignment;
use App\Models\DeliverySchedule;
use App\Models\Order;
use App\Models\RoutePlan;
use App\Models\ShipmentTracking;
use App\Models\TrackingStatus;
use App\Models\Trip;
use App\Models\TripOrder;
use App\Models\Vehicle;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TripController extends Controller
{
    public function index(Request $request)
    {
        abort_if(Gate::denies('order_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $query = Trip::with(['courier', 'vehicle', 'routePlan', 'branch'])->withCount('tripOrders')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('courier_id')) {
            $query->where('courier_id', $request->courier_id);
        }
        if ($request->filled('scheduled_date')) {
            $query->whereDate('scheduled_date', $request->scheduled_date);
        }

        $trips = $query->paginate(20)->withQueryString();
        $couriers = Courier::orderBy('name')->get();

        return view('admin.trips.index', compact('trips', 'couriers'));
    }

    public function create()
    {
        abort_if(Gate::denies('order_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $routePlans = RoutePlan::where('is_active', true)->orderBy('name')->get();
        $couriers = Courier::where('status', 'active')->orderBy('name')->get();
        $vehicles = Vehicle::whereIn('status', ['available', 'in_use'])->orderBy('plate_number')->get();
        $branches = Branch::orderBy('title_ar')->get();
        $orders = Order::with(['sender', 'recipient', 'latestTracking.trackingStatus'])->latest()->take(200)->get();
        $settings = [
            'pickup_window_from' => \App\Models\OperationalSetting::getValue('pickup_window_from', '08:00'),
            'pickup_window_to' => \App\Models\OperationalSetting::getValue('pickup_window_to', '15:00'),
            'delivery_window_from' => \App\Models\OperationalSetting::getValue('delivery_window_from', '16:00'),
            'delivery_window_to' => \App\Models\OperationalSetting::getValue('delivery_window_to', '23:00'),
        ];

        return view('admin.trips.create', compact('routePlans', 'couriers', 'vehicles', 'branches', 'orders', 'settings'));
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('order_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'route_plan_id' => 'nullable|exists:route_plans,id',
            'courier_id' => 'nullable|exists:couriers,id',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'branch_id' => 'nullable|exists:branches,id',
            'trip_type' => 'required|in:pickup,delivery,mixed,return,transfer',
            'trip_direction' => 'required|in:one_way,round_trip',
            'scheduled_date' => 'nullable|date',
            'pickup_window_from' => 'nullable|date_format:H:i',
            'pickup_window_to' => 'nullable|date_format:H:i',
            'delivery_window_from' => 'nullable|date_format:H:i',
            'delivery_window_to' => 'nullable|date_format:H:i',
            'estimated_distance_km' => 'nullable|numeric|min:0',
            'estimated_cost' => 'nullable|numeric|min:0',
            'fuel_cost' => 'nullable|numeric|min:0',
            'toll_cost' => 'nullable|numeric|min:0',
            'driver_fee' => 'nullable|numeric|min:0',
            'helper_fee' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'exists:orders,id',
            'default_task_type' => 'required|in:pickup,delivery,return,exchange',
        ]);

        $routePlan = !empty($data['route_plan_id']) ? RoutePlan::find($data['route_plan_id']) : null;

        $trip = new Trip($data);
        $trip->created_by = auth()->id();
        $trip->status = !empty($data['courier_id']) ? 'assigned' : 'planned';
        $trip->scheduled_date = $trip->scheduled_date ?: today();
        $trip->estimated_shipments_count = count($data['order_ids']);
        $trip->pickup_window_from = $data['pickup_window_from'] ?? optional($routePlan)->pickup_window_from;
        $trip->pickup_window_to = $data['pickup_window_to'] ?? optional($routePlan)->pickup_window_to;
        $trip->delivery_window_from = $data['delivery_window_from'] ?? optional($routePlan)->delivery_window_from;
        $trip->delivery_window_to = $data['delivery_window_to'] ?? optional($routePlan)->delivery_window_to;
        $trip->recalculateActualCost();
        $trip->save();

        foreach ($data['order_ids'] as $index => $orderId) {
            $taskType = $data['default_task_type'];
            $tripOrder = $trip->tripOrders()->create([
                'order_id' => $orderId,
                'stop_sequence' => $index + 1,
                'task_type' => $taskType,
                'status' => 'pending',
                'scheduled_time' => $trip->scheduled_date ? $trip->scheduled_date->format('Y-m-d') . ' ' . ($taskType === 'pickup' ? ($trip->pickup_window_from ?? '08:00') : ($trip->delivery_window_from ?? '16:00')) : null,
            ]);

            if ($trip->courier_id) {
                $assignment = CourierAssignment::create([
                    'order_id' => $orderId,
                    'courier_id' => $trip->courier_id,
                    'vehicle_id' => $trip->vehicle_id,
                    'assignment_type' => $taskType === 'exchange' ? 'delivery' : $taskType,
                    'status' => 'assigned',
                    'scheduled_at' => $tripOrder->scheduled_time,
                    'notes' => 'تم الإسناد عبر الرحلة ' . $trip->trip_number,
                    'assigned_by' => auth()->id(),
                ]);

                if (in_array($taskType, ['pickup', 'delivery'], true)) {
                    DeliverySchedule::updateOrCreate(
                        [
                            'order_id' => $orderId,
                            'attempt_number' => 1,
                        ],
                        [
                            'courier_id' => $trip->courier_id,
                            'scheduled_date' => $trip->scheduled_date,
                            'time_from' => $taskType === 'pickup' ? $trip->pickup_window_from : $trip->delivery_window_from,
                            'time_to' => $taskType === 'pickup' ? $trip->pickup_window_to : $trip->delivery_window_to,
                            'status' => 'scheduled',
                            'courier_notes' => 'ضمن الرحلة ' . $trip->trip_number,
                        ]
                    );
                }

                $this->createTrackingEvent($orderId, $trip, $assignment->id, $taskType, 'assigned', 'تم إسناد الطلب ضمن الرحلة ' . $trip->trip_number);
            }
        }

        return redirect()->route('admin.trips.show', $trip)->with('message', 'تم إنشاء الرحلة وربط الشحنات بنجاح');
    }

    public function show(Trip $trip)
    {
        abort_if(Gate::denies('order_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $trip->load([
            'courier.branch',
            'vehicle',
            'routePlan.stops.branch',
            'tripOrders.order.sender',
            'tripOrders.order.recipient',
            'tripOrders.order.latestTracking.trackingStatus',
        ]);

        return view('admin.trips.show', compact('trip'));
    }

    public function start(Trip $trip)
    {
        abort_if(Gate::denies('order_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $trip->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        foreach ($trip->tripOrders as $tripOrder) {
            if ($tripOrder->status === 'pending') {
                $tripOrder->update(['status' => 'in_transit']);
                $this->createTrackingEvent($tripOrder->order_id, $trip, null, $tripOrder->task_type, 'in_transit', 'بدء الرحلة ' . $trip->trip_number);
            }
        }

        CourierAssignment::whereIn('order_id', $trip->tripOrders->pluck('order_id'))
            ->where('courier_id', $trip->courier_id)
            ->where('status', 'assigned')
            ->update(['status' => 'accepted', 'accepted_at' => now()]);

        return back()->with('message', 'تم بدء الرحلة وتحديث حالات التتبع');
    }

    public function updateTripOrder(Request $request, Trip $trip, TripOrder $tripOrder)
    {
        abort_if(Gate::denies('order_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        abort_if($tripOrder->trip_id !== $trip->id, Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'status' => 'required|in:pending,loaded,in_transit,delivered,failed,returned',
            'notes' => 'nullable|string|max:1000',
        ]);

        $tripOrder->update([
            'status' => $data['status'],
            'notes' => $data['notes'] ?? $tripOrder->notes,
            'completed_at' => in_array($data['status'], ['delivered', 'failed', 'returned'], true) ? now() : null,
        ]);

        $this->createTrackingEvent($tripOrder->order_id, $trip, null, $tripOrder->task_type, $data['status'], $data['notes'] ?? ('تحديث حالة مهمة الرحلة ' . $trip->trip_number));

        CourierAssignment::where('order_id', $tripOrder->order_id)
            ->where('courier_id', $trip->courier_id)
            ->latest('id')
            ->first()
            ?->update([
                'status' => $this->mapAssignmentStatus($data['status']),
                'completed_at' => in_array($data['status'], ['delivered', 'failed', 'returned'], true) ? now() : null,
                'notes' => $data['notes'] ?? null,
            ]);

        if (!$trip->tripOrders()->whereNotIn('status', ['delivered', 'failed', 'returned'])->exists()) {
            $trip->update([
                'status' => 'completed',
                'completed_at' => now(),
                'actual_shipments_count' => $trip->tripOrders()->count(),
            ]);
        }

        return back()->with('message', 'تم تحديث مهمة الشحنة ضمن الرحلة');
    }

    protected function createTrackingEvent(int $orderId, Trip $trip, ?int $assignmentId, string $taskType, string $state, string $notes): void
    {
        $statusCode = $this->resolveTrackingCode($taskType, $state);
        $trackingStatus = $statusCode ? TrackingStatus::where('code', $statusCode)->first() : null;

        if (!$trackingStatus) {
            return;
        }

        ShipmentTracking::create([
            'order_id' => $orderId,
            'tracking_status_id' => $trackingStatus->id,
            'courier_id' => $trip->courier_id,
            'courier_assignment_id' => $assignmentId,
            'branch_id' => $trip->branch_id,
            'recorded_by' => auth()->id(),
            'updated_by_role' => 'admin',
            'notes' => $notes,
            'event_time' => now(),
        ]);

        Order::where('id', $orderId)->update(['order_status_id' => $trackingStatus->id]);
    }

    protected function resolveTrackingCode(string $taskType, string $state): ?string
    {
        $map = [
            'pickup' => [
                'assigned' => 'COURIER_ASSIGNED_PICKUP',
                'in_transit' => 'COURIER_ON_WAY_PICKUP',
                'loaded' => 'PICKED_UP',
                'delivered' => 'PICKED_UP',
                'failed' => 'FAILED_DELIVERY',
                'returned' => 'RETURNED_TO_SENDER',
            ],
            'delivery' => [
                'assigned' => 'COURIER_ASSIGNED_DELIVERY',
                'in_transit' => 'OUT_FOR_DELIVERY',
                'loaded' => 'OUT_FOR_DELIVERY',
                'delivered' => 'DELIVERED',
                'failed' => 'FAILED_DELIVERY',
                'returned' => 'RETURNED_TO_SENDER',
            ],
            'return' => [
                'assigned' => 'RETURNED_TO_BRANCH',
                'in_transit' => 'RETURN_IN_TRANSIT',
                'delivered' => 'RETURNED_TO_SENDER',
                'failed' => 'FAILED_DELIVERY',
                'returned' => 'RETURNED_TO_SENDER',
            ],
            'exchange' => [
                'assigned' => 'COURIER_ASSIGNED_DELIVERY',
                'in_transit' => 'OUT_FOR_DELIVERY',
                'delivered' => 'DELIVERED',
                'failed' => 'FAILED_DELIVERY',
                'returned' => 'RETURNED_TO_SENDER',
            ],
        ];

        return $map[$taskType][$state] ?? null;
    }

    protected function mapAssignmentStatus(string $status): string
    {
        $map = [
            'pending' => 'assigned',
            'loaded' => 'picked_up',
            'in_transit' => 'accepted',
            'delivered' => 'delivered',
            'failed' => 'failed',
            'returned' => 'cancelled',
        ];

        return $map[$status] ?? 'assigned';
    }
}