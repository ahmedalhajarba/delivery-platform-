<?php

namespace App\Services;

use App\Models\HrApprovalAction;
use App\Models\HrApprovalRequest;
use App\Models\HrApprovalWorkflow;
use Illuminate\Support\Facades\DB;

class HrApprovalService
{
    public function submit(string $requestType, array $payload = [], ?string $referenceType = null, ?int $referenceId = null): HrApprovalRequest
    {
        $workflow = HrApprovalWorkflow::query()
            ->where('request_type', $requestType)
            ->where('is_active', true)
            ->with('steps')
            ->latest('id')
            ->firstOrFail();

        if ($workflow->steps->isEmpty()) {
            throw new \RuntimeException('لا يوجد خطوات معرفة لمسار الموافقات المحدد.');
        }

        return HrApprovalRequest::create([
            'workflow_id' => $workflow->id,
            'request_type' => $requestType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'payload' => $payload,
            'requested_by' => auth()->id(),
            'current_step_order' => (int) $workflow->steps->min('step_order'),
            'status' => 'pending',
        ]);
    }

    public function act(HrApprovalRequest $approvalRequest, string $action, ?string $note = null): HrApprovalRequest
    {
        $allowedActions = ['approve', 'reject'];
        if (!in_array($action, $allowedActions, true)) {
            throw new \InvalidArgumentException('الإجراء غير صالح.');
        }

        if ($approvalRequest->status !== 'pending') {
            throw new \RuntimeException('لا يمكن تنفيذ إجراء على طلب موافقة غير معلّق.');
        }

        $approvalRequest->loadMissing('workflow.steps');
        $currentStep = $approvalRequest->workflow->steps
            ->firstWhere('step_order', (int) $approvalRequest->current_step_order);

        if (!$currentStep) {
            throw new \RuntimeException('لا توجد خطوة حالية صالحة لهذا الطلب.');
        }

        $this->assertCanActOnStep($currentStep);

        DB::transaction(function () use ($approvalRequest, $currentStep, $action, $note) {
            HrApprovalAction::create([
                'approval_request_id' => $approvalRequest->id,
                'workflow_step_id' => $currentStep->id,
                'actor_user_id' => auth()->id(),
                'action' => $action,
                'note' => $note,
            ]);

            if ($action === 'reject') {
                $approvalRequest->update([
                    'status' => 'rejected',
                    'rejected_at' => now(),
                ]);
                return;
            }

            $nextStep = $approvalRequest->workflow->steps
                ->where('step_order', '>', (int) $approvalRequest->current_step_order)
                ->sortBy('step_order')
                ->first();

            if ($nextStep) {
                $approvalRequest->update([
                    'current_step_order' => (int) $nextStep->step_order,
                    'status' => 'pending',
                ]);
            } else {
                $approvalRequest->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                ]);
            }
        });

        $fresh = $approvalRequest->fresh();

        if ($fresh->status === 'approved' && in_array($fresh->request_type, ['branch_freeze', 'branch_block'], true)) {
            app(BranchLifecycleService::class)->executeApprovedLifecycle($fresh);
        }

        return $fresh;
    }

    private function assertCanActOnStep($step): void
    {
        $user = auth()->user();
        if (!$user) {
            throw new \RuntimeException('المستخدم غير مصادق.');
        }

        if ($step->user_id && (int) $step->user_id === (int) $user->id) {
            return;
        }

        if ($step->role_id && $user->roles()->where('roles.id', $step->role_id)->exists()) {
            return;
        }

        if (!$step->user_id && !$step->role_id) {
            return;
        }

        throw new \RuntimeException('ليس لديك صلاحية اعتماد هذه الخطوة.');
    }
}
