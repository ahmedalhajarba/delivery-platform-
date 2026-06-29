<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchEmployee;
use App\Models\HrTask;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class HrTaskController extends Controller
{
    public function index(Request $request)
    {
        abort_if(Gate::denies('order_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $query = HrTask::with(['branch', 'assignedUser', 'assignedEmployee', 'creator'])->latest();

        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        $tasks = $query->paginate(25)->withQueryString();

        $branches = Branch::orderBy('title_ar')->get(['id', 'title_ar']);
        $users = User::orderBy('name')->get(['id', 'name']);
        $employees = BranchEmployee::orderBy('name')->get(['id', 'name']);

        return view('admin.hr-tasks.index', compact('tasks', 'branches', 'users', 'employees'));
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('order_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'module' => 'required|in:orders,couriers,branches,bookings,tickets,support,accounts,subscriptions,coupons,operations',
            'task_type' => 'required|string|max:60',
            'priority' => 'required|in:low,normal,high,urgent',
            'status' => 'required|in:open,in_progress,pending,completed,cancelled',
            'branch_id' => 'nullable|exists:branches,id',
            'assigned_user_id' => 'nullable|exists:users,id',
            'assigned_employee_id' => 'nullable|exists:branch_employees,id',
            'due_at' => 'nullable|date',
        ]);

        $data['created_by'] = auth()->id();

        HrTask::create($data);

        return back()->with('success', 'تم إنشاء مهمة HR بنجاح.');
    }

    public function updateStatus(Request $request, HrTask $hrTask)
    {
        abort_if(Gate::denies('order_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'status' => 'required|in:open,in_progress,pending,completed,cancelled',
        ]);

        $payload = ['status' => $data['status']];
        if ($data['status'] === 'completed' && !$hrTask->completed_at) {
            $payload['completed_at'] = now();
        }

        $hrTask->update($payload);

        return back()->with('success', 'تم تحديث حالة المهمة.');
    }
}
