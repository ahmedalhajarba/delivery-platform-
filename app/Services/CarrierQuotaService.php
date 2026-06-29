<?php

namespace App\Services;

use App\Models\CarrierMonthlyQuota;
use App\Models\CarrierMonthlyConsumption;
use App\Models\CarrierQuotaLog;
use App\Models\Order;
use App\Models\CarrierCompany;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class CarrierQuotaService
{
    /**
     * Get or create monthly consumption record.
     * 
     * @param int|null $carrierId
     * @param int|null $year
     * @param int|null $month
     * @return CarrierMonthlyConsumption|null
     */
    public function getOrCreateMonthlyConsumption(?int $carrierId = null, ?int $year = null, ?int $month = null): ?CarrierMonthlyConsumption
    {
        // إذا كان carrierId null، حاول الحصول على أول carrier
        if ($carrierId === null) {
            $carrier = CarrierCompany::first();
            if ($carrier) {
                $carrierId = $carrier->id;
            } else {
                // إذا لم يوجد أي carrier، قم بإنشاء واحد افتراضي
                $carrier = CarrierCompany::create([
                    'company_name' => 'شركة الشحن الافتراضية',
                    'name_ar' => 'شركة الشحن الافتراضية',
                    'status' => 'active',
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $carrierId = $carrier->id;
            }
        }

        $year = $year ?? now()->year;
        $month = $month ?? now()->month;

        return CarrierMonthlyConsumption::firstOrCreate(
            [
                'carrier_id' => $carrierId,
                'year' => $year,
                'month' => $month,
            ],
            [
                'subscription_used' => 0,
                'individual_used' => 0,
            ]
        );
    }

    /**
     * Validate if order can be created based on carrier quota.
     * 
     * @param Order $order
     * @param int $carrierId
     * @param string $orderType (subscription|individual)
     * 
     * @throws Exception
     */
    public function validateOrderQuota(Order $order, int $carrierId, string $orderType = 'individual'): void
    {
        $quota = CarrierMonthlyQuota::where('carrier_id', $carrierId)
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->where('is_active', true)
            ->first();

        // If no quota configured, allow order
        if (!$quota) {
            return;
        }

        $consumption = $this->getOrCreateMonthlyConsumption($carrierId);

        // Check if total quota exhausted
        if ($consumption->total_used >= $quota->total_waybills_cap) {
            $carrierName = $order->carrierCompany ? $order->carrierCompany->name : 'غير معروف';
            throw new Exception(
                "تم الوصول للحد الأقصى من البوالص لناقل {$carrierName} هذا الشهر ({$quota->total_waybills_cap}). " .
                "يرجى إعادة المحاولة الشهر القادم أو اختيار ناقل آخر."
            );
        }

        // Check subscription pool if order type is subscription
        if ($orderType === 'subscription' && $consumption->subscription_used >= $quota->subscription_pool_cap) {
            $carrierName = $order->carrierCompany ? $order->carrierCompany->name : 'غير معروف';
            throw new Exception(
                "تم الوصول للحد الأقصى من باقات الاشتراكات لناقل {$carrierName} ({$quota->subscription_pool_cap}). " .
                "يرجى اختيار ناقل آخر أو الشحن الفردي."
            );
        }
    }

    /**
     * Record order consumption (called after order is placed).
     * 
     * @param Order $order
     * @param int $carrierId
     * @param string $orderType (subscription|individual)
     */
    public function recordConsumption(Order $order, int $carrierId, string $orderType = 'individual'): void
    {
        DB::transaction(function () use ($order, $carrierId, $orderType) {
            $consumption = $this->getOrCreateMonthlyConsumption($carrierId);

            if ($orderType === 'subscription') {
                $consumption->incrementSubscription();
            } else {
                $consumption->incrementIndividual();
            }

            // Log the consumption
            CarrierQuotaLog::create([
                'carrier_id' => $carrierId,
                'order_id' => $order->id,
                'year' => now()->year,
                'month' => now()->month,
                'action' => $orderType === 'subscription' ? 'subscription_consume' : 'individual_consume',
                'order_type' => $orderType,
                'quantity_change' => 1,
                'reason' => "Order #{$order->id} created",
            ]);
        });
    }

    /**
     * Refund consumption (called when order is cancelled/returned).
     * 
     * @param Order $order
     * @param int $carrierId
     * @param string $orderType
     */
    public function refundConsumption(Order $order, int $carrierId, string $orderType = 'individual'): void
    {
        DB::transaction(function () use ($order, $carrierId, $orderType) {
            $consumption = $this->getOrCreateMonthlyConsumption($carrierId);

            if ($orderType === 'subscription') {
                $consumption->decrementSubscription();
            } else {
                $consumption->decrementIndividual();
            }

            // Log the refund
            CarrierQuotaLog::create([
                'carrier_id' => $carrierId,
                'order_id' => $order->id,
                'year' => now()->year,
                'month' => now()->month,
                'action' => $orderType === 'subscription' ? 'subscription_refund' : 'individual_refund',
                'order_type' => $orderType,
                'quantity_change' => -1,
                'reason' => "Order #{$order->id} cancelled/returned",
            ]);
        });
    }

    /**
     * Get quota utilization dashboard data for current month.
     */
    public function getCurrentMonthUtilization(): array
    {
        $year = now()->year;
        $month = now()->month;

        $quotas = CarrierMonthlyQuota::where('year', $year)
            ->where('month', $month)
            ->where('is_active', true)
            ->with('carrierCompany')
            ->get();

        $data = [];
        foreach ($quotas as $quota) {
            $consumption = $this->getOrCreateMonthlyConsumption($quota->carrier_id, $year, $month);
            
            $data[] = [
                'carrier' => $quota->carrierCompany->name ?? 'Unknown',
                'carrier_id' => $quota->carrier_id,
                'total_cap' => $quota->total_waybills_cap ?? 0,
                'subscription_pool_cap' => $quota->subscription_pool_cap ?? 0,
                'individual_pool_cap' => $quota->individual_pool_cap ?? 0,
                'subscription_used' => $consumption->subscription_used ?? 0,
                'individual_used' => $consumption->individual_used ?? 0,
                'total_used' => $consumption->total_used ?? 0,
                'subscription_remaining' => max(0, ($quota->subscription_pool_cap ?? 0) - ($consumption->subscription_used ?? 0)),
                'individual_remaining' => max(0, ($quota->individual_pool_cap ?? 0) - ($consumption->individual_used ?? 0)),
                'total_remaining' => max(0, ($quota->total_waybills_cap ?? 0) - ($consumption->total_used ?? 0)),
                'subscription_percentage' => ($quota->subscription_pool_cap ?? 0) > 0 
                    ? round((($consumption->subscription_used ?? 0) / ($quota->subscription_pool_cap ?? 1)) * 100, 2)
                    : 0,
                'total_percentage' => ($quota->total_waybills_cap ?? 0) > 0
                    ? round((($consumption->total_used ?? 0) / ($quota->total_waybills_cap ?? 1)) * 100, 2)
                    : 0,
                'is_subscription_exhausted' => ($consumption->subscription_used ?? 0) >= ($quota->subscription_pool_cap ?? 0),
                'is_total_exhausted' => ($consumption->total_used ?? 0) >= ($quota->total_waybills_cap ?? 0),
            ];
        }

        return $data;
    }

    /**
     * Get quota history/logs for a specific carrier and period.
     */
    public function getCarrierQuotaHistory(int $carrierId, int $year = null, int $month = null): array
    {
        $year = $year ?? now()->year;
        $month = $month ?? now()->month;

        return CarrierQuotaLog::where('carrier_id', $carrierId)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('created_at', 'desc')
            ->with(['order', 'user', 'carrierCompany'])
            ->get()
            ->toArray();
    }

    /**
     * Manually adjust quota (admin only).
     */
    public function manualAdjustment(int $carrierId, int $adjustmentQuantity, string $reason = '', int $userId = null): void
    {
        DB::transaction(function () use ($carrierId, $adjustmentQuantity, $reason, $userId) {
            $consumption = $this->getOrCreateMonthlyConsumption($carrierId);
            
            $consumption->update([
                'subscription_used' => max(0, ($consumption->subscription_used ?? 0) + $adjustmentQuantity),
            ]);

            CarrierQuotaLog::create([
                'carrier_id' => $carrierId,
                'year' => now()->year,
                'month' => now()->month,
                'action' => 'manual_adjustment',
                'quantity_change' => $adjustmentQuantity,
                'reason' => $reason,
                'user_id' => $userId,
            ]);
        });
    }

    /**
     * Reset monthly consumption for a carrier (should be run at start of each month).
     */
    public function resetMonthlyConsumption(int $carrierId, int $year, int $month): void
    {
        CarrierMonthlyConsumption::where('carrier_id', $carrierId)
            ->where('year', $year)
            ->where('month', $month)
            ->delete();

        CarrierQuotaLog::create([
            'carrier_id' => $carrierId,
            'year' => $year,
            'month' => $month,
            'action' => 'quota_reset',
            'quantity_change' => 0,
            'reason' => "Monthly quota reset for {$year}-{$month}",
        ]);
    }

    /**
     * Check quota alert thresholds.
     * Returns warning message if quota is approaching limit.
     * 
     * @param int|null $carrierId
     * @return string|null
     */
    public function getQuotaWarning(?int $carrierId = null): ?string
    {
        // إذا كان carrierId null، حاول الحصول على أول carrier
        if ($carrierId === null) {
            $carrier = CarrierCompany::first();
            if ($carrier) {
                $carrierId = $carrier->id;
            } else {
                return null;
            }
        }

        $quota = CarrierMonthlyQuota::where('carrier_id', $carrierId)
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->where('is_active', true)
            ->first();

        if (!$quota) {
            return null;
        }

        $consumption = $this->getOrCreateMonthlyConsumption($carrierId);
        
        $totalCap = $quota->total_waybills_cap ?? 1;
        $totalUsed = $consumption->total_used ?? 0;
        $totalPercentage = $totalCap > 0 ? ($totalUsed / $totalCap) * 100 : 0;
        
        $subscriptionCap = $quota->subscription_pool_cap ?? 1;
        $subscriptionUsed = $consumption->subscription_used ?? 0;
        $subscriptionPercentage = $subscriptionCap > 0 ? ($subscriptionUsed / $subscriptionCap) * 100 : 0;

        // Critical: 90%+ used
        if ($totalPercentage >= 90) {
            return "⚠️ تنبيه حرج: تم استهلاك " . round($totalPercentage) . "% من الحد الأقصى للناقل";
        }

        // Warning: 75%+ used
        if ($totalPercentage >= 75) {
            return "⚠️ تنبيه: تم استهلاك " . round($totalPercentage) . "% من الحد الأقصى للناقل";
        }

        // Subscription pool warning
        if ($subscriptionPercentage >= 80) {
            return "⚠️ تنبيه: تم استهلاك " . round($subscriptionPercentage) . "% من باقات الاشتراكات";
        }

        return null;
    }
}