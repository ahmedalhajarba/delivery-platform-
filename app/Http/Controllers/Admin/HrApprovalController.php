<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HrApprovalRequest;
use App\Models\HrApprovalWorkflow;
use App\Models\HrApprovalWorkflowStep;
use App\Models\Role;
use App\Models\User;
use App\Services\HrApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class HrApprovalController extends Controller
{
    public function workflows()
    {
        abort_if(Gate::denies('order_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $workflows = HrApprovalWorkflow::with('steps.role', 'steps.user')->latest()->get();
        $roles = Role::orderBy('title')->get(['id', 'title']);
        $users = User::orderBy('name')->get(['id', 'name']);

        return view('admin.hr-approvals.workflows', compact('workflows', 'roles', 'users'));
    }

    public function storeWorkflow(Request $request)
    {
        abort_if(Gate::denies('order_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'request_type' => 'required|in:account_activation,subscription_activation,coupon_discount,subscription_plan_change,account_status_change,branch_freeze,branch_block',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
            'steps' => 'required|array|min:1',
            'steps.*.step_order' => 'required|integer|min:1',
            'steps.*.role_id' => 'nullable|exists:roles,id',
            'steps.*.user_id' => 'nullable|exists:users,id',
            'steps.*.label' => 'nullable|string|max:255',
            'steps.*.is_required' => 'nullable|boolean',
        ]);

        $workflow = HrApprovalWorkflow::create([
            'name' => $data['name'],
            'request_type' => $data['request_type'],
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        foreach ($data['steps'] as $step) {
            HrApprovalWorkflowStep::create([
                'workflow_id' => $workflow->id,
                'step_order' => (int) $step['step_order'],
                'role_id' => $step['role_id'] ?? null,
                'user_id' => $step['user_id'] ?? null,
                'label' => $step['label'] ?? null,
                'is_required' => isset($step['is_required']) ? (bool) $step['is_required'] : true,
            ]);
        }

        return back()->with('success', 'تم إنشاء مسار الموافقات بنجاح.');
    }

    public function requests(Request $request)
    {
        abort_if(Gate::denies('order_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $query = HrApprovalRequest::with(['workflow.steps.role', 'workflow.steps.user', 'requester'])->latest();
        if ($request->filled('request_type')) {
            $query->where('request_type', $request->request_type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->paginate(25)->withQueryString();

        return view('admin.hr-approvals.requests', compact('requests'));
    }

    public function submit(Request $request, HrApprovalService $approvalService)
    {
        abort_if(Gate::denies('order_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'request_type' => 'required|in:account_activation,subscription_activation,coupon_discount,subscription_plan_change,account_status_change,branch_freeze,branch_block',
            'reference_type' => 'nullable|string|max:190',
            'reference_id' => 'nullable|integer',
            'payload_json' => 'nullable|string',
        ]);

        $payload = [];
        if (!empty($data['payload_json'])) {
            $decoded = json_decode($data['payload_json'], true);
            if (!is_array($decoded)) {
                return back()->withErrors(['payload_json' => 'صيغة JSON غير صحيحة.'])->withInput();
            }
            $payload = $decoded;
        }

        $approvalService->submit(
            requestType: $data['request_type'],
            payload: $payload,
            referenceType: $data['reference_type'] ?? null,
            referenceId: $data['reference_id'] ?? null
        );

        return back()->with('success', 'تم إرسال طلب الموافقة بنجاح.');
    }

    public function act(Request $request, HrApprovalRequest $hrApprovalRequest, HrApprovalService $approvalService)
    {
        abort_if(Gate::denies('order_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $data = $request->validate([
            'action' => 'required|in:approve,reject',
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            $approvalService->act($hrApprovalRequest, $data['action'], $data['note'] ?? null);
        } catch (\Throwable $e) {
            return back()->withErrors(['approval' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'تم تنفيذ الإجراء على طلب الموافقة.');
    }
}
