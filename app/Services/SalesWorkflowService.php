<?php

namespace App\Services;

use App\Models\AccountActivationRequest;
use App\Models\Contract;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SalesWorkflowService
{
    public function ensureActivationRequest(
        User $customer,
        ?Contract $contract = null,
        ?int $assignedTo = null,
        array $payload = []
    ): array {
        $existing = AccountActivationRequest::query()
            ->where('user_id', $customer->id)
            ->whereNotIn('status', ['rejected', 'activated'])
            ->when($contract, fn ($q) => $q->where('contract_id', $contract->id))
            ->latest('id')
            ->first();

        if ($existing) {
            return [$existing, false];
        }

        $fallbackExisting = AccountActivationRequest::query()
            ->where('user_id', $customer->id)
            ->whereNotIn('status', ['rejected', 'activated'])
            ->latest('id')
            ->first();

        if ($fallbackExisting) {
            if (!$fallbackExisting->contract_id && $contract) {
                $fallbackExisting->update(['contract_id' => $contract->id]);
            }

            return [$fallbackExisting, false];
        }

        $activation = null;
        DB::transaction(function () use ($customer, $contract, $assignedTo, $payload, &$activation) {
            $activation = AccountActivationRequest::create([
                'user_id'             => $customer->id,
                'contract_id'         => $contract?->id,
                'assigned_to'         => $assignedTo,
                'status'              => 'pending',
                'commercial_register' => $payload['commercial_register'] ?? null,
                'tax_number'          => $payload['tax_number'] ?? null,
                'id_document'         => $payload['id_document'] ?? null,
                'notes'               => $payload['notes'] ?? null,
            ]);

            $activation->initApprovalSteps();
            $this->syncCustomerAccountState($customer, 'documents_review');
        });

        return [$activation, true];
    }

    public function syncCustomerAccountState(User $customer, string $activationStatus, ?int $activeContractId = null): void
    {
        $profileStatus = $this->mapActivationStatusToProfileStatus($activationStatus);

        $userPayload = ['account_status' => $activationStatus];
        if ($activationStatus === 'activated') {
            $userPayload['status'] = 1;
        }
        if ($activeContractId) {
            $userPayload['active_contract_id'] = $activeContractId;
        }

        $customer->update($userPayload);

        $profile = $customer->profile()->firstOrCreate(
            ['user_id' => $customer->id],
            ['customer_code' => CustomerProfile::generateCode()]
        );

        $profilePayload = ['account_status' => $profileStatus];
        if ($activeContractId) {
            $profilePayload['active_contract_id'] = $activeContractId;
        }
        $profile->update($profilePayload);
    }

    private function mapActivationStatusToProfileStatus(string $activationStatus): string
    {
        if ($activationStatus === 'activated') {
            return 'active';
        }

        if ($activationStatus === 'rejected') {
            return 'suspended';
        }

        return 'pending';
    }
}
