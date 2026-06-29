<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\City;
use App\Models\RoutePlan;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoutePlanController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('order_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $routePlans = RoutePlan::with(['branch', 'stops'])->latest()->paginate(20);

        return view('admin.route-plans.index', compact('routePlans'));
    }

    public function create()
    {
        abort_if(Gate::denies('order_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branches = Branch::orderBy('title_ar')->get();
        $cities = City::orderBy('title_ar')->get();

        return view('admin.route-plans.create', compact('branches', 'cities'));
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('order_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:route_plans,code',
            'branch_id' => 'nullable|exists:branches,id',
            'trip_direction' => 'required|in:one_way,round_trip',
            'pickup_window_from' => 'nullable|date_format:H:i',
            'pickup_window_to' => 'nullable|date_format:H:i',
            'delivery_window_from' => 'nullable|date_format:H:i',
            'delivery_window_to' => 'nullable|date_format:H:i',
            'estimated_distance_km' => 'nullable|numeric|min:0',
            'estimated_duration_minutes' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string|max:2000',
            'stops' => 'nullable|array',
            'stops.*.stop_name' => 'required_with:stops|string|max:255',
            'stops.*.stop_type' => 'required_with:stops|in:origin,pickup,hub,delivery,destination,custom',
            'stops.*.branch_id' => 'nullable|exists:branches,id',
            'stops.*.city_id' => 'nullable|exists:cities,id',
            'stops.*.service_window_from' => 'nullable|date_format:H:i',
            'stops.*.service_window_to' => 'nullable|date_format:H:i',
            'stops.*.notes' => 'nullable|string|max:500',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $stops = $data['stops'] ?? [];
        unset($data['stops']);

        $routePlan = RoutePlan::create($data);

        foreach ($stops as $index => $stop) {
            $routePlan->stops()->create([
                'stop_order' => $index + 1,
                'stop_type' => $stop['stop_type'],
                'branch_id' => $stop['branch_id'] ?? null,
                'city_id' => $stop['city_id'] ?? null,
                'stop_name' => $stop['stop_name'],
                'service_window_from' => $stop['service_window_from'] ?? null,
                'service_window_to' => $stop['service_window_to'] ?? null,
                'notes' => $stop['notes'] ?? null,
            ]);
        }

        return redirect()->route('admin.route-plans.show', $routePlan)->with('message', 'تم حفظ خط السير بنجاح');
    }

    public function show(RoutePlan $routePlan)
    {
        abort_if(Gate::denies('order_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $routePlan->load(['branch', 'stops.branch', 'stops.city', 'trips.courier']);

        return view('admin.route-plans.show', compact('routePlan'));
    }
}