<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BranchEmployee;
use App\Models\CourierTripFinancial;
use Illuminate\Http\Request;

class CourierTripFinancialController extends Controller
{
    public function index(Request $request)
    {
        $query = CourierTripFinancial::query()->with('employee')->latest('trip_date');

        if ($request->filled('employee_id')) {
            $query->where('branch_employee_id', $request->employee_id);
        }

        if ($request->filled('from')) {
            $query->whereDate('trip_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('trip_date', '<=', $request->to);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $trips = $query->paginate(20)->appends($request->query());
        $employees = BranchEmployee::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.courier-trip-financials.index', compact('trips', 'employees'));
    }

    public function create()
    {
        $employees = BranchEmployee::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.courier-trip-financials.create', compact('employees'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_employee_id' => ['required', 'exists:branch_employees,id'],
            'trip_code' => ['nullable', 'string', 'max:50'],
            'trip_date' => ['required', 'date'],
            'base_wage' => ['nullable', 'numeric', 'min:0'],
            'commission_amount' => ['nullable', 'numeric'],
            'bonus_amount' => ['nullable', 'numeric'],
            'deduction_amount' => ['nullable', 'numeric'],
            'operational_cost' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,approved,paid,cancelled'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['base_wage'] = $validated['base_wage'] ?? 0;
        $validated['commission_amount'] = $validated['commission_amount'] ?? 0;
        $validated['bonus_amount'] = $validated['bonus_amount'] ?? 0;
        $validated['deduction_amount'] = $validated['deduction_amount'] ?? 0;
        $validated['operational_cost'] = $validated['operational_cost'] ?? 0;
        $validated['created_by'] = auth()->id();

        CourierTripFinancial::create($validated);

        return redirect()->route('admin.courier-trip-financials.index')
            ->with('message', 'تم إضافة حركة الرحلة المالية بنجاح.');
    }

    public function show(CourierTripFinancial $courierTripFinancial)
    {
        $courierTripFinancial->load(['employee', 'creator']);

        return view('admin.courier-trip-financials.show', compact('courierTripFinancial'));
    }
}
