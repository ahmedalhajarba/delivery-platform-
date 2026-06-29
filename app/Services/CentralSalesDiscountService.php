<?php

namespace App\Services;

use App\Models\SalesDiscountCode;
use App\Models\SalesDiscountUsage;
use App\Models\User;
use Carbon\Carbon;
use App\Services\SalesCommissionEngineService;

class CentralSalesDiscountService
{
    protected $commissionEngine;

    public function __construct(SalesCommissionEngineService $commissionEngine)
    {
        $this->commissionEngine = $commissionEngine;
    }

    public function apply(?string $code, User $customer, float $subtotal, string $actorRole = 'system'): array
    {
        $subtotal = max(0, (float) $subtotal);
        $discountAmount = 0.0;
        $discountCode = null;

        if (!$code || trim($code) === '') {
            return [
                'discount_code' => null,
                'discount_amount' => 0.0,
                'final_total' => $subtotal,
            ];
        }

        $normalizedCode = strtoupper(trim($code));

        /** @var SalesDiscountCode|null $discountCode */
        $discountCode = SalesDiscountCode::query()
            ->where('code', $normalizedCode)
            ->where('is_active', true)
            ->first();

        if (!$discountCode) {
            return [
                'discount_code' => null,
                'discount_amount' => 0.0,
                'final_total' => $subtotal,
                'error' => 'كود الخصم غير صالح أو غير مفعل.',
            ];
        }

        if (!$this->isRoleAllowed($discountCode->allowed_role, $actorRole)) {
            return [
                'discount_code' => null,
                'discount_amount' => 0.0,
                'final_total' => $subtotal,
                'error' => 'هذا الكود غير مسموح لدور المستخدم الحالي.',
            ];
        }

        $now = Carbon::now();
        if ($discountCode->starts_at && $now->lt($discountCode->starts_at)) {
            return [
                'discount_code' => null,
                'discount_amount' => 0.0,
                'final_total' => $subtotal,
                'error' => 'كود الخصم لم يبدأ بعد.',
            ];
        }
        if ($discountCode->ends_at && $now->gt($discountCode->ends_at)) {
            return [
                'discount_code' => null,
                'discount_amount' => 0.0,
                'final_total' => $subtotal,
                'error' => 'كود الخصم منتهي.',
            ];
        }

        if ($discountCode->usage_limit !== null && (int) $discountCode->used_count >= (int) $discountCode->usage_limit) {
            return [
                'discount_code' => null,
                'discount_amount' => 0.0,
                'final_total' => $subtotal,
                'error' => 'تم استهلاك الحد الأعلى للكود.',
            ];
        }

        if ($discountCode->scope === 'selected_customers') {
            $allowed = $discountCode->customers()->where('users.id', $customer->id)->exists();
            if (!$allowed) {
                return [
                    'discount_code' => null,
                    'discount_amount' => 0.0,
                    'final_total' => $subtotal,
                    'error' => 'الكود غير متاح لهذا العميل.',
                ];
            }
        }

        if ($discountCode->discount_type === 'percent') {
            $discountAmount = round($subtotal * ((float) $discountCode->discount_value / 100), 2);
        } else {
            $discountAmount = round((float) $discountCode->discount_value, 2);
        }

        if ($discountCode->max_discount_amount !== null) {
            $discountAmount = min($discountAmount, (float) $discountCode->max_discount_amount);
        }

        $discountAmount = min($discountAmount, $subtotal);
        $finalTotal = round(max(0, $subtotal - $discountAmount), 2);

        return [
            'discount_code' => $discountCode,
            'discount_amount' => $discountAmount,
            'final_total' => $finalTotal,
        ];
    }

    public function recordUsage(SalesDiscountCode $code, User $customer, float $subtotal, float $discountAmount, float $finalTotal, ?int $invoiceId = null, string $actorRole = 'system'): void
    {
        $usage = SalesDiscountUsage::query()->create([
            'sales_discount_code_id' => $code->id,
            'user_id' => $customer->id,
            'invoice_id' => $invoiceId,
            'applied_by' => auth()->id(),
            'actor_role' => $actorRole,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'final_total' => $finalTotal,
            'applied_at' => now(),
        ]);

        $code->increment('used_count');

        // Auto-generate commission from coupon sales for the owning/referring sales user.
        $salesOwnerId = $code->owner_sales_user_id ?: $customer->referred_by_sales_user_id;
        if ($salesOwnerId) {
            $salesUser = User::query()->find($salesOwnerId);
            if ($salesUser) {
                $this->commissionEngine->createCouponSaleCommission($usage, $code, $salesUser);
            }
        }
    }

    private function isRoleAllowed(string $allowedRole, string $actorRole): bool
    {
        if ($allowedRole === 'both') {
            return in_array($actorRole, ['sales', 'finance', 'system'], true);
        }

        if ($actorRole === 'system') {
            return true;
        }

        return $allowedRole === $actorRole;
    }
}
