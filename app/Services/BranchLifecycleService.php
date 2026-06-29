<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchEmployee;
use App\Models\BranchServiceArea;
use App\Models\Courier;
use App\Models\HrApprovalRequest;
use Illuminate\Support\Facades\DB;

class BranchLifecycleService
{
    public function submitFreezeApproval(Branch $branch, ?string $reason = null): HrApprovalRequest
    {
        return $this->submitLifecycleApproval('branch_freeze', $branch, $reason);
    }

    public function submitBlockApproval(Branch $branch, ?string $reason = null): HrApprovalRequest
    {
        return $this->submitLifecycleApproval('branch_block', $branch, $reason);
    }

    public function executeApprovedLifecycle(HrApprovalRequest $approvalRequest): void
    {
        $approvalRequest->loadMissing('workflow');

        if ($approvalRequest->status !== 'approved') {
            throw new \RuntimeException('لا يمكن تنفيذ إجراء فرع قبل اكتمال الموافقة.');
        }

        if ($approvalRequest->reference_type !== Branch::class || empty($approvalRequest->reference_id)) {
            throw new \RuntimeException('مرجع طلب الموافقة غير صالح لإجراء الفرع.');
        }

        $branch = Branch::findOrFail((int) $approvalRequest->reference_id);
        $reason = data_get($approvalRequest->payload, 'reason');

        if ($approvalRequest->request_type === 'branch_freeze') {
            $this->applyFreeze($branch, $reason, $approvalRequest->id);
            return;
        }

        if ($approvalRequest->request_type === 'branch_block') {
            $this->applyBlock($branch, $reason, $approvalRequest->id);
            return;
        }

        throw new \RuntimeException('نوع طلب الموافقة غير مدعوم لإجراءات الفروع.');
    }

    public function applyFreeze(Branch $branch, ?string $reason = null, ?int $approvalRequestId = null): void
    {
        DB::transaction(function () use ($branch, $reason, $approvalRequestId) {
            $note = $reason;
            if ($approvalRequestId) {
                $note = trim(($note ? $note . ' | ' : '') . "approved_request:#{$approvalRequestId}");
            }

            $branch->update([
                'is_frozen' => true,
                'is_blocked' => false,
                'intake_enabled' => false,
                'status_changed_at' => now(),
                'status_changed_by' => auth()->id(),
                'status_note' => $note,
            ]);

            BranchServiceArea::where('branch_id', $branch->id)->update(['is_active' => false]);

            BranchEmployee::where('branch_id', $branch->id)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);

            Courier::where('branch_id', $branch->id)
                ->update([
                    'branch_id' => null,
                    'is_available' => false,
                    'status' => 'suspended',
                    'updated_at' => now(),
                ]);
        });
    }

    public function applyBlock(Branch $branch, ?string $reason = null, ?int $approvalRequestId = null): void
    {
        $note = $reason;
        if ($approvalRequestId) {
            $note = trim(($note ? $note . ' | ' : '') . "approved_request:#{$approvalRequestId}");
        }

        $branch->update([
            'is_frozen' => false,
            'is_blocked' => true,
            'intake_enabled' => false,
            'status_changed_at' => now(),
            'status_changed_by' => auth()->id(),
            'status_note' => $note,
        ]);

        BranchServiceArea::where('branch_id', $branch->id)->update(['is_active' => false]);
    }

    private function submitLifecycleApproval(string $requestType, Branch $branch, ?string $reason = null): HrApprovalRequest
    {
        $existingPending = HrApprovalRequest::query()
            ->where('request_type', $requestType)
            ->where('reference_type', Branch::class)
            ->where('reference_id', $branch->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if ($existingPending) {
            throw new \RuntimeException('يوجد طلب موافقة معلّق لهذا الإجراء على نفس الفرع.');
        }

        return app(HrApprovalService::class)->submit(
            requestType: $requestType,
            payload: [
                'branch_id' => $branch->id,
                'branch_title_ar' => $branch->title_ar,
                'reason' => $reason,
                'requested_action' => $requestType,
            ],
            referenceType: Branch::class,
            referenceId: $branch->id,
        );
    }
}
