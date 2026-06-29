<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\CourierAssignment;
use App\Models\CourierBooking;
use App\Models\Order;
use App\Models\ShipmentTracking;
use App\Models\TrackingStatus;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CourierBookingController extends Controller
{
    public function index(Request $request)
    {
        abort_if(Gate::denies('order_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $query = CourierBooking::with(['user', 'order', 'courier'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('service_type')) {
            $query->where('service_type', $request->service_type);
        }

        $bookings = $query->paginate(20)->withQueryString();

        return view('admin.courier-bookings.index', compact('bookings'));
    }

    public function show(CourierBooking $courierBooking)
    {
        abort_if(Gate::denies('order_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $courierBooking->load(['user', 'order', 'senderAddress', 'recipientAddress', 'courier']);
        $couriers = Courier::where('status', 'active')->orderBy('name')->get();

        return view('admin.courier-bookings.show', compact('courierBooking', 'couriers'));
    }

    public function update(Request $request, CourierBooking $courierBooking)
    {
        abort_if(Gate::denies('order_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'status' => 'required|in:new,confirmed,assigned,in_progress,completed,cancelled',
            'courier_id' => 'nullable|exists:couriers,id',
            'admin_notes' => 'nullable|string|max:2000',
        ]);

        if ($data['status'] === 'confirmed' && !$courierBooking->confirmed_at) {
            $data['confirmed_at'] = now();
        }
        if ($data['status'] === 'completed' && !$courierBooking->completed_at) {
            $data['completed_at'] = now();
        }

        $courierBooking->update($data);

        if (!empty($data['courier_id']) && $courierBooking->order_id) {
            CourierAssignment::updateOrCreate([
                'order_id' => $courierBooking->order_id,
                'courier_id' => $data['courier_id'],
                'assignment_type' => $courierBooking->service_type === 'delivery' ? 'delivery' : 'pickup',
            ], [
                'order_id' => $courierBooking->order_id,
                'courier_id' => $data['courier_id'],
                'assignment_type' => $courierBooking->service_type === 'delivery' ? 'delivery' : 'pickup',
                'status' => 'assigned',
                'scheduled_at' => $courierBooking->requested_date ? $courierBooking->requested_date->format('Y-m-d') . ' ' . ($courierBooking->requested_time_from ?? '08:00') : null,
                'notes' => 'إسناد من طلب حجز ' . $courierBooking->booking_number,
                'assigned_by' => auth()->id(),
            ]);

            $statusCode = $courierBooking->service_type === 'delivery' ? 'COURIER_ASSIGNED_DELIVERY' : 'COURIER_ASSIGNED_PICKUP';
            $trackingStatus = TrackingStatus::where('code', $statusCode)->first();

            if ($trackingStatus) {
                ShipmentTracking::create([
                    'order_id' => $courierBooking->order_id,
                    'tracking_status_id' => $trackingStatus->id,
                    'courier_id' => $data['courier_id'],
                    'recorded_by' => auth()->id(),
                    'updated_by_role' => 'admin',
                    'notes' => 'إسناد من حجز مندوب ' . $courierBooking->booking_number,
                    'event_time' => now(),
                ]);

                Order::where('id', $courierBooking->order_id)->update(['order_status_id' => $trackingStatus->id]);
            }

            \App\Models\HrTask::updateOrCreate(
                [
                    'related_type' => \App\Models\CourierBooking::class,
                    'related_id' => $courierBooking->id,
                    'task_type' => 'booking_assignment',
                ],
                [
                    'title' => 'متابعة حجز مندوب ' . $courierBooking->booking_number,
                    'description' => 'تم إسناد الحجز لمندوب ويجب متابعة التنفيذ.',
                    'module' => 'bookings',
                    'priority' => 'normal',
                    'status' => in_array($data['status'], ['completed', 'cancelled'], true) ? 'completed' : 'in_progress',
                    'created_by' => auth()->id(),
                    'due_at' => now()->addHours(3),
                ]
            );
        }

        return back()->with('message', 'تم تحديث طلب حجز المندوب');
    }
}