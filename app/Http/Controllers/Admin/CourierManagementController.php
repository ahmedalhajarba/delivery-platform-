<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Courier;
use App\Models\CourierAssignment;
use App\Models\Order;
use App\Models\ShipmentTracking;
use App\Models\TrackingStatus;
use App\Models\Vehicle;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CourierManagementController extends Controller
{
    // ─── قائمة المناديب ──────────────────────────────────────────────────────
    public function index(Request $request)
    {
        abort_if(Gate::denies('order_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $query = Courier::with(['branch', 'vehicle'])->withCount([
            'activeAssignments',
            'assignments as today_count' => fn($q) => $q->whereDate('assigned_at', today()),
        ]);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $couriers = $query->orderBy('name')->paginate(20)->withQueryString();
        $branches = Branch::orderBy('title_ar')->get();

        return view('admin.courier-management.index', compact('couriers', 'branches'));
    }

    // ─── إنشاء مندوب جديد ───────────────────────────────────────────────────
    public function create()
    {
        abort_if(Gate::denies('order_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branches = Branch::orderBy('title_ar')->pluck('title_ar', 'id');
        $vehicles = Vehicle::where('status', 'available')->orderBy('plate_number')->pluck('plate_number', 'id');

        return view('admin.courier-management.create', compact('branches', 'vehicles'));
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('order_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'employee_id'      => 'nullable|string|max:50|unique:couriers,employee_id',
            'national_id'      => 'nullable|string|max:20',
            'mobile'           => 'required|string|max:20|unique:couriers,mobile',
            's_mobile'         => 'nullable|string|max:20',
            'email'            => 'nullable|email|max:255',
            'type'             => 'required|in:pickup,delivery,both',
            'branch_id'        => 'nullable|exists:branches,id',
            'vehicle_id'       => 'nullable|exists:vehicles,id',
            'max_daily_orders' => 'nullable|integer|min:1|max:500',
            'contract_start'   => 'nullable|date',
            'contract_end'     => 'nullable|date|after_or_equal:contract_start',
            'notes'            => 'nullable|string|max:1000',
        ]);

        Courier::create($data);

        return redirect()->route('admin.courier-management.index')
            ->with('message', 'تم إضافة المندوب بنجاح');
    }

    // ─── تعديل مندوب ────────────────────────────────────────────────────────
    public function edit(Courier $courierManagement)
    {
        abort_if(Gate::denies('order_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branches = Branch::orderBy('title_ar')->pluck('title_ar', 'id');
        $vehicles = Vehicle::whereIn('status', ['available', 'in_use'])->orderBy('plate_number')->pluck('plate_number', 'id');

        return view('admin.courier-management.edit', compact('courierManagement', 'branches', 'vehicles'));
    }

    public function update(Request $request, Courier $courierManagement)
    {
        abort_if(Gate::denies('order_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'employee_id'      => 'nullable|string|max:50|unique:couriers,employee_id,' . $courierManagement->id,
            'national_id'      => 'nullable|string|max:20',
            'mobile'           => 'required|string|max:20|unique:couriers,mobile,' . $courierManagement->id,
            's_mobile'         => 'nullable|string|max:20',
            'email'            => 'nullable|email|max:255',
            'type'             => 'required|in:pickup,delivery,both',
            'branch_id'        => 'nullable|exists:branches,id',
            'vehicle_id'       => 'nullable|exists:vehicles,id',
            'status'           => 'required|in:active,on_leave,suspended,terminated',
            'is_available'     => 'boolean',
            'max_daily_orders' => 'nullable|integer|min:1|max:500',
            'contract_start'   => 'nullable|date',
            'contract_end'     => 'nullable|date|after_or_equal:contract_start',
            'notes'            => 'nullable|string|max:1000',
        ]);

        $courierManagement->update($data);

        return redirect()->route('admin.courier-management.index')
            ->with('message', 'تم تحديث بيانات المندوب بنجاح');
    }

    public function destroy(Courier $courierManagement)
    {
        abort_if(Gate::denies('order_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        $courierManagement->delete();
        return back()->with('message', 'تم حذف المندوب');
    }

    // ─── تخصيص طلبات للمندوب ────────────────────────────────────────────────
    public function assignOrders(Request $request)
    {
        abort_if(Gate::denies('order_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'courier_id'      => 'required|exists:couriers,id',
            'order_ids'       => 'required|array|min:1',
            'order_ids.*'     => 'exists:orders,id',
            'assignment_type' => 'required|in:pickup,delivery,transfer,return',
            'scheduled_at'    => 'nullable|date',
            'notes'           => 'nullable|string|max:500',
            'vehicle_id'      => 'nullable|exists:vehicles,id',
        ]);

        $courier    = Courier::findOrFail($data['courier_id']);
        $statusCode = $data['assignment_type'] === 'pickup'
            ? 'COURIER_ASSIGNED_PICKUP'
            : 'COURIER_ASSIGNED_DELIVERY';

        $trackingStatus = TrackingStatus::where('code', $statusCode)->first();

        foreach ($data['order_ids'] as $orderId) {
            // إنشاء التخصيص
            CourierAssignment::create([
                'order_id'        => $orderId,
                'courier_id'      => $data['courier_id'],
                'vehicle_id'      => $data['vehicle_id'] ?? $courier->vehicle_id,
                'assignment_type' => $data['assignment_type'],
                'status'          => 'assigned',
                'scheduled_at'    => $data['scheduled_at'] ?? null,
                'notes'           => $data['notes'] ?? null,
                'assigned_by'     => auth()->id(),
            ]);

            // تحديث حالة التتبع
            if ($trackingStatus) {
                ShipmentTracking::create([
                    'order_id'           => $orderId,
                    'tracking_status_id' => $trackingStatus->id,
                    'courier_id'         => $data['courier_id'],
                    'recorded_by'        => auth()->id(),
                    'notes'              => 'تم تعيين المندوب: ' . $courier->name,
                    'event_time'         => now(),
                    'updated_by_role'    => 'admin',
                ]);
                Order::where('id', $orderId)->update(['order_status_id' => $trackingStatus->id]);
            }
        }

        return back()->with('message', 'تم تخصيص ' . count($data['order_ids']) . ' طلب للمندوب بنجاح');
    }

    // ─── لوحة مهام مندوب ────────────────────────────────────────────────────
    public function courierBoard(Courier $courierManagement)
    {
        abort_if(Gate::denies('order_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $assignments = CourierAssignment::where('courier_id', $courierManagement->id)
            ->whereIn('status', ['assigned', 'accepted', 'picked_up'])
            ->with(['order.sender', 'order.recipient', 'order.latestTracking.trackingStatus'])
            ->orderBy('scheduled_at')
            ->get();

        $completedToday = CourierAssignment::where('courier_id', $courierManagement->id)
            ->whereDate('completed_at', today())
            ->where('status', 'delivered')
            ->count();

        return view('admin.courier-management.board', compact('courierManagement', 'assignments', 'completedToday'));
    }
}
