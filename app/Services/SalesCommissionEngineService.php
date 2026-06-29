<?php

namespace App\Services;

use App\Models\BillingSetting;
use App\Models\SalesCommission;
use App\Models\SalesDiscountCode;
use App\Models\SalesDiscountUsage;
use App\Models\User;

class SalesCommissionEngineService
{
    public function shouldAutoGenerate(string $channel): bool
    {
        $settings = BillingSetting::current();

        if (($settings->settlement_mode ?? 'manual') !== 'auto') {
            return false;
        }

        $mode = $settings->auto_settlement_generation ?? 'none';

        if ($mode === 'both') {
            return true;
        }

        if ($channel === 'referral' && $mode === 'referral') {
            return true;
        }

        if ($channel === 'coupon' && $mode === 'coupon') {
            return true;
        }

        return false;
    }

    public function createRegistrationCommission(User $customer, User $salesUser, ?SalesDiscountCode $discountCode = null): ?SalesCommission
    {
        $settings = BillingSetting::current();

        if (!$this->shouldAutoGenerate('referral')) {
            return null;
        }

        if (!(bool) ($settings->referral_new_customer_commission_enabled ?? false)) {
            return null;
        }

        $commissionAmount = (float) ($settings->referral_new_customer_commission_amount ?? 0);
        if ($commissionAmount <= 0) {
            return null;
        }

        $sourceId = $customer->id;

        return SalesCommission::query()->updateOrCreate(
            [
                'user_id' => $salesUser->id,
                'role_type' => 'sales',
                'source_type' => 'registration',
                'source_id' => $sourceId,
            ],
            [
                'period_year' => now()->year,
                'period_month' => now()->month,
                'base_amount' => 0,
                'commission_amount' => $commissionAmount,
                'bonus_amount' => 0,
                'net_amount' => $commissionAmount,
                'target_amount' => 0,
                'achieved_amount' => 0,
                'target_achievement_percent' => 0,
                'incentive_adjustment' => 0,
                'discount_impact_amount' => 0,
                'settlement_amount' => $commissionAmount,
                'status' => 'pending',
                'settlement_status' => 'pending',
                'calculated_at' => now(),
                'notes' => 'عمولة تسجيل عميل جديد عبر رابط إحالة المبيعات' . ($discountCode ? (' / كود: ' . $discountCode->code) : ''),
            ]
        );
    }

    public function createCouponSaleCommission(SalesDiscountUsage $usage, SalesDiscountCode $code, User $salesUser): ?SalesCommission
    {
        $settings = BillingSetting::current();

        if (!$this->shouldAutoGenerate('coupon')) {
            return null;
        }

        if (!(bool) ($settings->coupon_sales_commission_enabled ?? false)) {
            return null;
        }

        $percent = (float) ($settings->coupon_sales_commission_percent ?? 0);
        if ($percent <= 0) {
            return null;
        }

        $base = (float) $usage->final_total;
        $commissionAmount = round(($base * $percent) / 100, 2);

        if ($commissionAmount <= 0) {
            return null;
        }

        return SalesCommission::query()->updateOrCreate(
            [
                'user_id' => $salesUser->id,
                'role_type' => 'sales',
                'source_type' => 'discount_usage',
                'source_id' => $usage->id,
            ],
            [
                'period_year' => now()->year,
                'period_month' => now()->month,
                'base_amount' => $base,
                'commission_amount' => $commissionAmount,
                'bonus_amount' => 0,
                'net_amount' => $commissionAmount,
                'target_amount' => 0,
                'achieved_amount' => $base,
                'target_achievement_percent' => 0,
                'incentive_adjustment' => 0,
                'discount_impact_amount' => (float) $usage->discount_amount,
                'settlement_amount' => $commissionAmount,
                'status' => 'pending',
                'settlement_status' => 'pending',
                'calculated_at' => now(),
                'notes' => 'عمولة بيع عبر كود خصم المبيعات: ' . $code->code,
            ]
        );
    }
}
