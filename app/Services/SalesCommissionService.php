<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\SalesCommission;
use App\Models\SalesIncentiveRule;
use App\Models\User;

class SalesCommissionService
{
    public function createOrUpdateInvoiceSalesCommission(Invoice $invoice, ?User $salesUser): ?SalesCommission
    {
        if (!$salesUser) {
            return null;
        }

        $base = (float) $invoice->total_amount;

        $rule = SalesIncentiveRule::query()
            ->where('role_type', 'sales')
            ->where('basis', 'invoice')
            ->where('is_active', true)
            ->where('min_amount', '<=', $base)
            ->orderByDesc('min_amount')
            ->first();

        $commission = 0.0;
        $bonus = 0.0;

        if ($rule) {
            if ($rule->commission_type === 'percent') {
                $commission = round($base * ((float) $rule->commission_value / 100), 2);
            } else {
                $commission = round((float) $rule->commission_value, 2);
            }

            if ($rule->bonus_threshold_amount !== null && $base >= (float) $rule->bonus_threshold_amount) {
                $bonus = round((float) $rule->bonus_amount, 2);
            }
        }

        return SalesCommission::query()->updateOrCreate(
            [
                'user_id' => $salesUser->id,
                'role_type' => 'sales',
                'source_type' => 'invoice',
                'source_id' => $invoice->id,
            ],
            [
                'base_amount' => $base,
                'commission_amount' => $commission,
                'bonus_amount' => $bonus,
                'net_amount' => round($commission + $bonus, 2),
                'status' => 'pending',
                'calculated_at' => now(),
                'notes' => 'احتساب تلقائي من نظام الفوترة.',
            ]
        );
    }
}
