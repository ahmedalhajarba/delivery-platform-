<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Vehicle;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VehicleController extends Controller
{
    public function __construct()
    {
        $this->middleware('check.permission:order_access')->only('index');
        $this->middleware('check.permission:order_create')->only(['create', 'store']);
        $this->middleware('check.permission:order_edit')->only(['edit', 'update']);
        $this->middleware('check.permission:order_delete')->only('destroy');
    }
    public function index()
    {
        $vehicles = Vehicle::with(['branch', 'couriers'])->orderBy('plate_number')->paginate(20);
        return view('admin.vehicles.index', compact('vehicles'));
    }

    public function create()
    {
        $branches = Branch::orderBy('title_ar')->pluck('title_ar', 'id');
        return view('admin.vehicles.create', compact('branches'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'plate_number'        => 'required|string|max:20|unique:vehicles,plate_number',
            'vehicle_type'        => 'required|string',
            'brand'               => 'nullable|string|max:100',
            'model'               => 'nullable|string|max:100',
            'manufacture_year'    => 'nullable|integer|min:1990|max:' . (date('Y') + 1),
            'color'               => 'nullable|string|max:50',
            'max_weight_kg'       => 'nullable|numeric|min:0',
            'max_volume_m3'       => 'nullable|numeric|min:0',
            'branch_id'           => 'nullable|exists:branches,id',
            'status'              => 'required|in:available,in_use,maintenance,retired',
            'insurance_expiry'    => 'nullable|date',
            'registration_expiry' => 'nullable|date',
            'last_maintenance'    => 'nullable|date',
            'notes'               => 'nullable|string|max:1000',
        ]);

        Vehicle::create($data);

        return redirect()->route('admin.vehicles.index')
            ->with('message', 'تم إضافة المركبة بنجاح');
    }

    public function edit(Vehicle $vehicle)
    {
        $branches = Branch::orderBy('title_ar')->pluck('title_ar', 'id');
        return view('admin.vehicles.edit', compact('vehicle', 'branches'));
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $data = $request->validate([
            'plate_number'        => 'required|string|max:20|unique:vehicles,plate_number,' . $vehicle->id,
            'vehicle_type'        => 'required|string',
            'brand'               => 'nullable|string|max:100',
            'model'               => 'nullable|string|max:100',
            'manufacture_year'    => 'nullable|integer|min:1990|max:' . (date('Y') + 1),
            'color'               => 'nullable|string|max:50',
            'max_weight_kg'       => 'nullable|numeric|min:0',
            'max_volume_m3'       => 'nullable|numeric|min:0',
            'branch_id'           => 'nullable|exists:branches,id',
            'status'              => 'required|in:available,in_use,maintenance,retired',
            'insurance_expiry'    => 'nullable|date',
            'registration_expiry' => 'nullable|date',
            'last_maintenance'    => 'nullable|date',
            'notes'               => 'nullable|string|max:1000',
        ]);

        $vehicle->update($data);

        return redirect()->route('admin.vehicles.index')
            ->with('message', 'تم تحديث بيانات المركبة');
    }

    public function destroy(Vehicle $vehicle)
    {
        $vehicle->delete();
        return back()->with('message', 'تم حذف المركبة');
    }
}
