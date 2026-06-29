<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchCapacityLog;
use App\Models\BranchDailyConsumption;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BranchCapacityService
{
    public function getOrCreateDailyConsumption(int $branchId, ?Carbon $date = null): BranchDailyConsumption
    {
        $date = $date ?: now();

        return BranchDailyConsumption::firstOrCreate(
            [
                'branch_id' => $branchId,
                'date' => $date->toDateString(),
            ],
            [
                'subscription_used' => 0,
                'regular_used' => 0,
            ]
        );
    }

    public function validateOrderCapacity(int $branchId, string $orderType): void
    {
        $branch = Branch::findOrFail($branchId);

        if ($branch->is_blocked) {
            throw new \Exception("فرع {$branch->title_ar} محظور حالياً ولا يمكن استقبال الطلبات عليه.");
        }

        if ($branch->is_frozen) {
            throw new \Exception("فرع {$branch->title_ar} مجمّد حالياً وتم إيقاف تحويل الطلبات عليه.");
        }

        if (!$branch->intake_enabled) {
            throw new \Exception("فرع {$branch->title_ar} متوقف حالياً عن استقبال الطلبات.");
        }

        if (empty($branch->daily_waybills_cap)) {
            // No cap configured, keep default behavior.
            return;
        }

        $consumption = $this->getOrCreateDailyConsumption($branchId);
        $dailyCap = (int) $branch->daily_waybills_cap;
        $reservedSubscriptions = min((int) $branch->subscription_reserved_cap, $dailyCap);
        $regularCap = max(0, $dailyCap - $reservedSubscriptions);
        $isSubscription = $orderType === 'subscription';
        $totalUsed = (int) $consumption->subscription_used + (int) $consumption->regular_used;

        if (($totalUsed + 1) > $dailyCap) {
            throw new \Exception("تم الوصول إلى الطاقة الاستيعابية اليومية لفرع {$branch->title_ar} ({$dailyCap} بوليصة). يرجى المحاولة لاحقاً.");
        }

        if (!$isSubscription && ($consumption->regular_used + 1) > $regularCap) {
            throw new \Exception("تم استنفاد حصة الطلبات العادية في فرع {$branch->title_ar} ({$regularCap} بوليصة). سيتم استقبال طلبات المشتركين فقط حالياً.");
        }
    }

    public function recordConsumption(Order $order): void
    {
        if (!$order->origin_branch_id) {
            return;
        }

        DB::transaction(function () use ($order) {
            $consumption = $this->getOrCreateDailyConsumption((int) $order->origin_branch_id);
            $isSubscription = $order->order_type === 'subscription';

            if ($isSubscription) {
                $consumption->increment('subscription_used');
            } else {
                $consumption->increment('regular_used');
            }

            BranchCapacityLog::create([
                'branch_id' => $order->origin_branch_id,
                'order_id' => $order->id,
                'date' => now()->toDateString(),
                'action' => $isSubscription ? 'subscription_consume' : 'regular_consume',
                'order_type' => $order->order_type,
                'quantity_change' => 1,
                'reason' => 'استهلاك تلقائي عند إنشاء الطلب',
                'user_id' => auth()->id(),
            ]);
        });
    }

    public function getTodayUtilization(): array
    {
        $today = now()->toDateString();

        return Branch::orderBy('title_ar')->get()->map(function (Branch $branch) use ($today) {
            $consumption = BranchDailyConsumption::firstOrCreate(
                ['branch_id' => $branch->id, 'date' => $today],
                ['subscription_used' => 0, 'regular_used' => 0]
            );

            $dailyCap = (int) ($branch->daily_waybills_cap ?? 0);
            $reservedSubscriptions = min((int) ($branch->subscription_reserved_cap ?? 0), $dailyCap);
            $regularCap = max(0, $dailyCap - $reservedSubscriptions);
            $totalUsed = (int) $consumption->subscription_used + (int) $consumption->regular_used;

            $totalPercentage = $dailyCap > 0 ? round(($totalUsed / $dailyCap) * 100, 1) : 0;
            $regularPercentage = $regularCap > 0 ? round(($consumption->regular_used / $regularCap) * 100, 1) : 0;

            return [
                'branch_id' => $branch->id,
                'branch_name' => $branch->title_ar,
                'intake_enabled' => (bool) $branch->intake_enabled,
                'daily_cap' => $dailyCap,
                'subscription_reserved_cap' => $reservedSubscriptions,
                'regular_cap' => $regularCap,
                'subscription_used' => (int) $consumption->subscription_used,
                'regular_used' => (int) $consumption->regular_used,
                'total_used' => $totalUsed,
                'total_remaining' => max(0, $dailyCap - $totalUsed),
                'regular_remaining' => max(0, $regularCap - (int) $consumption->regular_used),
                'total_percentage' => $totalPercentage,
                'regular_percentage' => $regularPercentage,
                'is_total_exhausted' => $dailyCap > 0 && $totalUsed >= $dailyCap,
                'is_regular_exhausted' => $regularCap > 0 && (int) $consumption->regular_used >= $regularCap,
            ];
        })->toArray();
    }

    public function updateBranchCapacity(Branch $branch, array $data, ?int $userId = null): Branch
    {
        $branch->update([
            'daily_waybills_cap' => $data['daily_waybills_cap'] ?? null,
            'subscription_reserved_cap' => $data['subscription_reserved_cap'] ?? 0,
            'intake_enabled' => (bool) ($data['intake_enabled'] ?? false),
        ]);

        BranchCapacityLog::create([
            'branch_id' => $branch->id,
            'date' => now()->toDateString(),
            'action' => 'capacity_update',
            'quantity_change' => 0,
            'reason' => 'تحديث إعدادات السعة اليومية للفرع',
            'user_id' => $userId,
        ]);

        return $branch;
    }

    public function getLogs(?string $date = null, ?int $branchId = null)
    {
        $query = BranchCapacityLog::with(['branch', 'order', 'user'])
            ->when($date, function ($q) use ($date) {
                $q->whereDate('date', $date);
            })
            ->when($branchId, function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            })
            ->orderBy('created_at', 'desc');

        return $query->paginate(50);
    }
}
