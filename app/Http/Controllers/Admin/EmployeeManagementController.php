<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\CustomerSalesFollowup;
use App\Models\EmployeeKpiTarget;
use App\Models\EmployeeStatusLog;
use App\Models\HrTask;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EmployeeManagementController extends Controller
{
    private const STATUSES = ['pending_activation', 'active', 'on_leave', 'suspended'];

    public function index(Request $request)
    {
        $employeesQuery = $this->employeesBaseQuery();

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $employeesQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && in_array($request->status, self::STATUSES, true)) {
            $employeesQuery->where('employee_status', $request->status);
        }

        $employees = $employeesQuery
            ->with(['roles', 'city'])
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($request->query());

        $baseStatsQuery = $this->employeesBaseQuery();

        $stats = [
            'total' => (clone $baseStatsQuery)->count(),
            'active' => (clone $baseStatsQuery)->where('employee_status', 'active')->count(),
            'pending_activation' => (clone $baseStatsQuery)->where(function ($q) {
                $q->where('employee_status', 'pending_activation')->orWhereNull('employee_status');
            })->count(),
            'on_leave' => (clone $baseStatsQuery)->where('employee_status', 'on_leave')->count(),
            'suspended' => (clone $baseStatsQuery)->where('employee_status', 'suspended')->count(),
        ];

        return view('admin.employee-management.index', compact('employees', 'stats'));
    }

    public function show(Request $request, User $user)
    {
        $this->abortIfNotEmployee($user);

        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd = (clone $periodStart)->endOfMonth();

        $taskQuery = HrTask::query()
            ->where('assigned_user_id', $user->id)
            ->whereBetween('created_at', [$periodStart, $periodEnd]);

        $ticketQuery = SupportTicket::query()
            ->where('assigned_to', $user->id)
            ->whereBetween('created_at', [$periodStart, $periodEnd]);

        $followupQuery = CustomerSalesFollowup::query()
            ->where('sales_user_id', $user->id)
            ->whereBetween('followup_date', [$periodStart->toDateString(), $periodEnd->toDateString()]);

        $orderQuery = Order::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$periodStart, $periodEnd]);

        $quotationQuery = Quotation::query()
            ->where('created_by', $user->id)
            ->whereBetween('created_at', [$periodStart, $periodEnd]);

        $contractQuery = Contract::query()
            ->where('created_by', $user->id)
            ->whereBetween('created_at', [$periodStart, $periodEnd]);

        $actual = [
            'tasks_total' => (clone $taskQuery)->count(),
            'tasks_completed' => (clone $taskQuery)->where(function ($q) {
                $q->where('status', 'completed')->orWhereNotNull('completed_at');
            })->count(),
            'tickets_total' => (clone $ticketQuery)->count(),
            'tickets_resolved' => (clone $ticketQuery)->whereIn('status', ['resolved', 'closed'])->count(),
            'orders_total' => (clone $orderQuery)->count(),
            'followups_total' => (clone $followupQuery)->count(),
            'followups_done' => (clone $followupQuery)->where('status', 'done')->count(),
            'followups_achieved_amount' => (float) (clone $followupQuery)->sum('achieved_amount'),
            'quotations_total' => (clone $quotationQuery)->count(),
            'contracts_total' => (clone $contractQuery)->count(),
        ];

        $kpiTarget = EmployeeKpiTarget::query()
            ->where('user_id', $user->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $statusLogs = EmployeeStatusLog::query()
            ->with('changedBy')
            ->where('user_id', $user->id)
            ->latest('changed_at')
            ->limit(15)
            ->get();

        $recentTasks = HrTask::query()
            ->with('branch')
            ->where('assigned_user_id', $user->id)
            ->latest('id')
            ->limit(12)
            ->get();

        $recentTickets = SupportTicket::query()
            ->with(['customer'])
            ->where('assigned_to', $user->id)
            ->latest('id')
            ->limit(12)
            ->get();

        $recentFollowups = CustomerSalesFollowup::query()
            ->with(['customer', 'branch'])
            ->where('sales_user_id', $user->id)
            ->latest('id')
            ->limit(12)
            ->get();

        $recentQuotations = Quotation::query()
            ->with('user')
            ->where('created_by', $user->id)
            ->latest('id')
            ->limit(8)
            ->get();

        $recentContracts = Contract::query()
            ->with('user')
            ->where('created_by', $user->id)
            ->latest('id')
            ->limit(8)
            ->get();

        return view('admin.employee-management.show', compact(
            'user',
            'year',
            'month',
            'actual',
            'kpiTarget',
            'statusLogs',
            'recentTasks',
            'recentTickets',
            'recentFollowups',
            'recentQuotations',
            'recentContracts'
        ));
    }

    public function updateStatus(Request $request, User $user)
    {
        $this->abortIfNotEmployee($user);

        $validated = $request->validate([
            'employee_status' => ['required', 'in:' . implode(',', self::STATUSES)],
            'employee_department' => ['nullable', 'string', 'max:255'],
            'employee_position' => ['nullable', 'string', 'max:255'],
            'employee_hired_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $oldStatus = $user->employee_status ?: 'pending_activation';
        $newStatus = $validated['employee_status'];

        $payload = [
            'employee_status' => $newStatus,
            'employee_department' => $validated['employee_department'] ?? null,
            'employee_position' => $validated['employee_position'] ?? null,
            'employee_hired_at' => $validated['employee_hired_at'] ?? null,
        ];

        if ($newStatus === 'active' && !$user->employee_activated_at) {
            $payload['employee_activated_at'] = now();
            $payload['employee_activated_by'] = auth()->id();
        }

        if (in_array($newStatus, ['pending_activation', 'suspended'], true)) {
            $payload['employee_activated_at'] = null;
            $payload['employee_activated_by'] = null;
        }

        $user->update($payload);

        EmployeeStatusLog::create([
            'user_id' => $user->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'note' => $validated['note'] ?? null,
            'changed_by' => auth()->id(),
            'changed_at' => now(),
        ]);

        return redirect()
            ->route('admin.employee-management.show', $user)
            ->with('success', 'تم تحديث حالة الموظف وتسجيل المتابعة الإدارية بنجاح.');
    }

    public function upsertKpiTarget(Request $request, User $user)
    {
        $this->abortIfNotEmployee($user);

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'target_tasks' => ['nullable', 'integer', 'min:0'],
            'target_tickets' => ['nullable', 'integer', 'min:0'],
            'target_orders' => ['nullable', 'integer', 'min:0'],
            'target_followups' => ['nullable', 'integer', 'min:0'],
            'target_revenue' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        EmployeeKpiTarget::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'year' => (int) $validated['year'],
                'month' => (int) $validated['month'],
            ],
            [
                'target_tasks' => (int) ($validated['target_tasks'] ?? 0),
                'target_tickets' => (int) ($validated['target_tickets'] ?? 0),
                'target_orders' => (int) ($validated['target_orders'] ?? 0),
                'target_followups' => (int) ($validated['target_followups'] ?? 0),
                'target_revenue' => (float) ($validated['target_revenue'] ?? 0),
                'notes' => $validated['notes'] ?? null,
                'set_by' => auth()->id(),
            ]
        );

        return redirect()
            ->route('admin.employee-management.show', ['user' => $user->id, 'year' => $validated['year'], 'month' => $validated['month']])
            ->with('success', 'تم حفظ مستهدفات KPI للموظف بنجاح.');
    }

    private function employeesBaseQuery()
    {
        return User::query()
            ->where(function ($q) {
                $q->whereNull('user_type')->orWhere('user_type', '!=', 'customer');
            })
            ->whereHas('roles', function ($q) {
                $q->whereNotIn('title', ['customer']);
            });
    }

    private function abortIfNotEmployee(User $user): void
    {
        $isEmployee = $this->employeesBaseQuery()->where('id', $user->id)->exists();
        abort_unless($isEmployee, 404);
    }
}
