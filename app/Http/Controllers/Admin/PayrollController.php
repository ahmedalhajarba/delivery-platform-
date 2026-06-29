<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDeduction;
use App\Models\EmployeePayroll;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeePayroll::with(['user', 'approver', 'payer'])->latest('payroll_month');

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->user_id);
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->status);
        }

        if ($request->filled('month')) {
            try {
                $month = Carbon::createFromFormat('Y-m', (string) $request->month)->startOfMonth()->toDateString();
                $query->whereDate('payroll_month', $month);
            } catch (\Throwable $e) {
            }
        }

        $payrolls = $query->paginate(20)->appends($request->query());
        $employees = $this->employeesQuery()->orderBy('name')->get(['id', 'name', 'email', 'employee_position']);

        return view('admin.payroll.index', compact('payrolls', 'employees'));
    }

    public function create()
    {
        $employees = $this->employeesQuery()->orderBy('name')->get(['id', 'name', 'email', 'employee_position']);
        return view('admin.payroll.create', compact('employees'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'payroll_month' => ['required', 'date_format:Y-m'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'insurance_amount' => ['nullable', 'numeric', 'min:0'],
            'allowances_amount' => ['nullable', 'numeric', 'min:0'],
            'bonuses_amount' => ['nullable', 'numeric', 'min:0'],
            'incentives_amount' => ['nullable', 'numeric', 'min:0'],
            'manual_deductions_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,approved,paid,cancelled'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $payrollMonth = Carbon::createFromFormat('Y-m', (string) $data['payroll_month'])->startOfMonth();
        $monthStart = $payrollMonth->copy()->startOfMonth();
        $monthEnd = $payrollMonth->copy()->endOfMonth();

        $basicSalary = (float) $data['basic_salary'];
        $insurance = (float) ($data['insurance_amount'] ?? 0);
        $allowances = (float) ($data['allowances_amount'] ?? 0);
        $bonuses = (float) ($data['bonuses_amount'] ?? 0);
        $incentives = (float) ($data['incentives_amount'] ?? 0);
        $manualDeductions = (float) ($data['manual_deductions_amount'] ?? 0);

        $deductions = EmployeeDeduction::query()
            ->where('user_id', (int) $data['user_id'])
            ->where('is_active', true)
            ->where(function ($q) use ($monthEnd) {
                $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', $monthEnd->toDateString());
            })
            ->where(function ($q) use ($monthStart) {
                $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', $monthStart->toDateString());
            })
            ->get();

        $scheduledDeductions = $deductions->sum(function (EmployeeDeduction $deduction) use ($basicSalary) {
            return $deduction->resolveAmount($basicSalary);
        });

        $gross = round($basicSalary + $insurance + $allowances + $bonuses + $incentives, 2);
        $totalDeductions = round($scheduledDeductions + $manualDeductions, 2);
        $net = round($gross - $totalDeductions, 2);

        $payload = [
            'basic_salary' => $basicSalary,
            'insurance_amount' => $insurance,
            'allowances_amount' => $allowances,
            'bonuses_amount' => $bonuses,
            'incentives_amount' => $incentives,
            'scheduled_deductions_amount' => $scheduledDeductions,
            'manual_deductions_amount' => $manualDeductions,
            'total_deductions_amount' => $totalDeductions,
            'gross_amount' => $gross,
            'net_amount' => $net,
            'status' => (string) $data['status'],
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
            'approved_by' => in_array($data['status'], ['approved', 'paid'], true) ? auth()->id() : null,
            'paid_by' => $data['status'] === 'paid' ? auth()->id() : null,
            'paid_at' => $data['status'] === 'paid' ? now() : null,
        ];

        $payroll = EmployeePayroll::query()->updateOrCreate(
            [
                'user_id' => (int) $data['user_id'],
                'payroll_month' => $payrollMonth->toDateString(),
            ],
            $payload
        );

        return redirect()
            ->route('admin.payroll.show', $payroll)
            ->with('success', 'تم حفظ كشف الراتب بنجاح.');
    }

    public function show(EmployeePayroll $payroll)
    {
        $payroll->load(['user', 'creator', 'approver', 'payer']);

        $deductions = EmployeeDeduction::query()
            ->where('user_id', $payroll->user_id)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get();

        return view('admin.payroll.show', compact('payroll', 'deductions'));
    }

    public function print(EmployeePayroll $payroll)
    {
        $payroll->load(['user', 'creator', 'approver', 'payer']);

        return view('admin.payroll.print', compact('payroll'));
    }

    private function employeesQuery()
    {
        return User::query()
            ->where(function ($q) {
                $q->whereNull('user_type')->orWhere('user_type', '!=', 'customer');
            })
            ->whereHas('roles', function ($q) {
                $q->whereNotIn('title', ['customer']);
            });
    }
}
