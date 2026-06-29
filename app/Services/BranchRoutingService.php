<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Branch;
use App\Models\BranchEmployee;
use App\Models\BranchServiceArea;

class BranchRoutingService
{
    public function resolveBranchForOrderPayload(array $payload): ?Branch
    {
        $address = $this->extractRecipientLocation($payload);

        if (!$address['city_id'] && !$address['neighborhood_id'] && !$address['governorate_id']) {
            return null;
        }

        $query = BranchServiceArea::query()
            ->with('branch')
            ->where('is_active', true)
            ->whereHas('branch', function ($q) {
                $q->whereNull('deleted_at')
                    ->where('intake_enabled', true)
                    ->where('is_frozen', false)
                    ->where('is_blocked', false);
            });

        if ($address['neighborhood_id']) {
            $area = (clone $query)
                ->where('neighborhood_id', $address['neighborhood_id'])
                ->orderByDesc('priority')
                ->first();

            if ($area?->branch) {
                return $area->branch;
            }
        }

        if ($address['city_id']) {
            $area = (clone $query)
                ->whereNull('neighborhood_id')
                ->where('city_id', $address['city_id'])
                ->orderByDesc('priority')
                ->first();

            if ($area?->branch) {
                return $area->branch;
            }

            $branch = Branch::query()
                ->whereHas('cities', function ($q) use ($address) {
                    $q->where('cities.id', $address['city_id']);
                })
                ->where('intake_enabled', true)
                ->where('is_frozen', false)
                ->where('is_blocked', false)
                ->orderBy('title_ar')
                ->first();

            if ($branch) {
                return $branch;
            }
        }

        if ($address['governorate_id']) {
            $area = (clone $query)
                ->whereNull('neighborhood_id')
                ->whereNull('city_id')
                ->where('governorate_id', $address['governorate_id'])
                ->orderByDesc('priority')
                ->first();

            if ($area?->branch) {
                return $area->branch;
            }
        }

        return null;
    }

    public function pickBranchEmployeeForOperations(int $branchId): ?BranchEmployee
    {
        $keywords = ['عمليات', 'operations', 'متابعة', 'إشراف'];

        $employee = BranchEmployee::query()
            ->where('branch_id', $branchId)
            ->where(function ($q) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $q->orWhere('job_title_ar', 'like', "%{$keyword}%")
                        ->orWhere('job_title_en', 'like', "%{$keyword}%");
                }
            })
            ->orderBy('id')
            ->first();

        if ($employee) {
            return $employee;
        }

        return BranchEmployee::query()
            ->where('branch_id', $branchId)
            ->orderBy('id')
            ->first();
    }

    private function extractRecipientLocation(array $payload): array
    {
        $location = [
            'governorate_id' => null,
            'city_id' => null,
            'neighborhood_id' => null,
        ];

        if (!empty($payload['recipient_id'])) {
            $recipient = Address::find((int) $payload['recipient_id']);
            if ($recipient) {
                $location['governorate_id'] = $recipient->governorate_id;
                $location['city_id'] = $recipient->city_id;
                $location['neighborhood_id'] = $recipient->neighborhood_id;
                return $location;
            }
        }

        $location['governorate_id'] = $payload['recipient_new_governorate_id'] ?? null;
        $location['city_id'] = $payload['recipient_new_city_id'] ?? null;
        $location['neighborhood_id'] = $payload['recipient_new_neighborhood_id'] ?? null;

        return $location;
    }
}
