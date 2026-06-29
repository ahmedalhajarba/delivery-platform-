<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BranchEmployee;
use App\Models\CourierSettlement;
use App\Models\CourierTripFinancial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourierSettlementController extends Controller
{
    public function index(Request $request)
    {
        $query = CourierSettlement::query()->with('employee')->latest('settlement_date');

        if ($request->filled('employee_id')) {
            $query->where('branch_employee_id', $request->employee_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from')) {
            $query->whereDate('settlement_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('settlement_date', '<=', $request->to);
        }

        $settlements = $query->paginate(20)->appends($request->query());
        $employees = BranchEmployee::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.courier-settlements.index', compact('settlements', 'employees'));
    }

    public function create(Request $request)
    {
        $employees = BranchEmployee::query()->orderBy('name')->get(['id', 'name']);
        $selectedEmployee = $request->filled('employee_id') ? BranchEmployee::find($request->employee_id) : null;
        $from = $request->input('from');
        $to = $request->input('to');

        $trips = collect();
        $totals = [
            'base' => 0,
            'commission' => 0,
            'bonus' => 0,
            'deduction' => 0,
            'operational' => 0,
            'net' => 0,
        ];

        if ($selectedEmployee && $from && $to) {
            $trips = $this->getPendingTrips($selectedEmployee->id, $from, $to)->get();
            $totals = $this->buildTotals($trips);
        }

        return view('admin.courier-settlements.create', compact('employees', 'selectedEmployee', 'from', 'to', 'trips', 'totals'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_employee_id' => ['required', 'exists:branch_employees,id'],
            'settlement_date' => ['required', 'date'],
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return DB::transaction(function () use ($validated) {
            $trips = $this->getPendingTrips(
                (int) $validated['branch_employee_id'],
                $validated['period_from'],
                $validated['period_to']
            )->lockForUpdate()->get();

            if ($trips->isEmpty()) {
                return back()->withErrors(['period_from' => 'لا توجد رحلات مالية غير مسواة في الفترة المحددة.'])->withInput();
            }

            $totals = $this->buildTotals($trips);

            $settlement = CourierSettlement::create([
                'branch_employee_id' => $validated['branch_employee_id'],
                'settlement_date' => $validated['settlement_date'],
                'period_from' => $validated['period_from'],
                'period_to' => $validated['period_to'],
                'total_base_amount' => $totals['base'],
                'total_commission_amount' => $totals['commission'],
                'total_bonus_amount' => $totals['bonus'],
                'total_deduction_amount' => $totals['deduction'],
                'total_operational_cost' => $totals['operational'],
                'net_amount' => $totals['net'],
                'paid_amount' => 0,
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($trips as $trip) {
                $settlement->items()->create([
                    'courier_trip_financial_id' => $trip->id,
                    'net_amount' => $trip->net_amount,
                ]);
            }

            return redirect()->route('admin.courier-settlements.show', $settlement)
                ->with('message', 'تم إنشاء تسوية المندوب بنجاح.');
        });
    }

    public function show(CourierSettlement $courierSettlement)
    {
        $courierSettlement->load(['employee', 'items.tripFinancial']);

        return view('admin.courier-settlements.show', compact('courierSettlement'));
    }

    public function pay(CourierSettlement $courierSettlement, Request $request)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($courierSettlement->status === 'cancelled') {
            return back()->withErrors(['amount' => 'لا يمكن الدفع لتسوية ملغاة.']);
        }

        $newPaid = round((float) $courierSettlement->paid_amount + (float) $validated['amount'], 2);
        if ($newPaid > (float) $courierSettlement->net_amount) {
            return back()->withErrors(['amount' => 'المبلغ يتجاوز صافي التسوية.']);
        }

        $courierSettlement->paid_amount = $newPaid;
        $courierSettlement->paid_by = auth()->id();
        $courierSettlement->paid_at = now();

        if ($newPaid == (float) $courierSettlement->net_amount) {
            $courierSettlement->status = 'paid';
            CourierTripFinancial::query()
                ->whereIn('id', $courierSettlement->items()->pluck('courier_trip_financial_id'))
                ->update(['status' => 'paid']);
        } else {
            $courierSettlement->status = 'partial';
        }

        if (!empty($validated['notes'])) {
            $existingNotes = trim((string) $courierSettlement->notes);
            $courierSettlement->notes = trim($existingNotes . PHP_EOL . '[دفعة] ' . $validated['notes']);
        }

        $courierSettlement->save();

        return back()->with('message', 'تم تسجيل الدفعة بنجاح.');
    }

    private function getPendingTrips(int $employeeId, string $from, string $to)
    {
        return CourierTripFinancial::query()
            ->where('branch_employee_id', $employeeId)
            ->whereDate('trip_date', '>=', $from)
            ->whereDate('trip_date', '<=', $to)
            ->where('status', 'approved')
            ->whereDoesntHave('settlementItems')
            ->orderBy('trip_date');
    }

    private function buildTotals($trips): array
    {
        return [
            'base' => (float) $trips->sum('base_wage'),
            'commission' => (float) $trips->sum('commission_amount'),
            'bonus' => (float) $trips->sum('bonus_amount'),
            'deduction' => (float) $trips->sum('deduction_amount'),
            'operational' => (float) $trips->sum('operational_cost'),
            'net' => (float) $trips->sum('net_amount'),
        ];
    }
}
