<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Order;
use App\Models\SubscriptionCarrierSettlement;
use App\Models\SubscriptionCoupon;
use App\Models\SubscriptionCouponUsage;
use App\Models\SubscriptionExtraCharge;
use App\Models\SubscriptionPlanCarrier;
use App\Models\SubscriptionRenewal;
use App\Models\SubscriptionsPlan;
use App\Models\TaxSetting;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\UserSubscriptionCarrierBalance;
use App\Services\CentralFinanceBillingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * SubscriptionService
 *
 * المنطق التجاري الكامل لنظام الاشتراكات:
 *  - ربط الطلبات بالاشتراك الفعّال
 *  - خصم الشحنات
 *  - التجديد وحساب الرصيد المُهدر
 *  - حساب المقاصة مع COD
 */
class SubscriptionService
{
    public function canCreateSubscriptionOrder(int $userId): array
    {
        $subscription = $this->getActiveSubscription($userId);

        if (!$subscription) {
            return [
                'allowed' => false,
                'message' => 'لا يوجد اشتراك نشط للعميل. لا يمكن إنشاء طلب اشتراك.',
                'subscription' => null,
            ];
        }

        if ((int) $subscription->remaining_shipments <= 0) {
            return [
                'allowed' => false,
                'message' => 'رصيد الباقة صفر. لا يمكن إنشاء طلب جديد حتى التجديد.',
                'subscription' => $subscription,
            ];
        }

        return [
            'allowed' => true,
            'message' => 'مسموح.',
            'subscription' => $subscription,
        ];
    }

    /**
     * خصم رصيد الاشتراك عند بدء الاستلام (PICKED_UP)
     */
    public function consumeShipmentOnPickup(Order $order, ?int $trackingCarrierCompanyId = null): array
    {
        if ((string) $order->order_type !== 'subscription') {
            return ['success' => true, 'message' => 'الطلب ليس اشتراكاً مدفوعاً مسبقاً.'];
        }

        if ($order->subscription_deducted_at) {
            return ['success' => true, 'message' => 'تم خصم هذه الشحنة مسبقاً.'];
        }

        $userId = (int) $order->user_id;
        if ($userId <= 0) {
            return ['success' => false, 'message' => 'الطلب غير مرتبط بعميل صالح.'];
        }

        $subscription = $order->userSubscription;
        if (!$subscription || !$subscription->isActive()) {
            $subscription = $this->getActiveSubscription($userId);
        }

        if (!$subscription) {
            return ['success' => false, 'message' => 'لا يوجد اشتراك نشط للعميل.'];
        }

        if ((int) $subscription->remaining_shipments <= 0) {
            return ['success' => false, 'message' => 'رصيد الباقة صفر.'];
        }

        $resolvedCarrierId = $this->resolveOrderCarrierCompanyId($order, $trackingCarrierCompanyId);

        DB::transaction(function () use ($order, $subscription, $resolvedCarrierId) {
            $order->update(['user_subscription_id' => $subscription->id]);

            $activeCarrierBalances = UserSubscriptionCarrierBalance::query()
                ->where('user_subscription_id', $subscription->id)
                ->where('is_active', true)
                ->get();

            $consumedCarrierId = null;

            if ($activeCarrierBalances->isNotEmpty()) {
                $requestedBalance = $resolvedCarrierId
                    ? $activeCarrierBalances->firstWhere('carrier_company_id', (int) $resolvedCarrierId)
                    : null;

                $requestedPrice = (float) ($requestedBalance?->price_per_shipment ?? 0);
                $targetBalance = null;

                if ($requestedBalance && (int) $requestedBalance->remaining_shipments > 0) {
                    $targetBalance = $requestedBalance;
                }

                if (!$targetBalance) {
                    $allowFallback = (bool) ($subscription->subscription?->allow_cross_carrier_fallback ?? true);
                    if (!$allowFallback) {
                        throw new \RuntimeException('لا يوجد رصيد كافٍ في رصيد الناقل المطلوب، والتحويل بين الناقلات غير مسموح.');
                    }

                    $targetBalance = $activeCarrierBalances
                        ->where('remaining_shipments', '>', 0)
                        ->sortByDesc('remaining_shipments')
                        ->first();

                    if (!$targetBalance) {
                        throw new \RuntimeException('جميع أرصدة الناقلات ضمن الاشتراك مستنفذة.');
                    }
                }

                if (!$targetBalance->deductOne()) {
                    throw new \RuntimeException('تعذر خصم رصيد الناقل للشحنة.');
                }

                $consumedCarrierId = (int) $targetBalance->carrier_company_id;
                $consumedPrice = (float) $targetBalance->price_per_shipment;

                if (
                    $resolvedCarrierId
                    && $consumedCarrierId > 0
                    && (int) $resolvedCarrierId !== $consumedCarrierId
                ) {
                    SubscriptionCarrierSettlement::create([
                        'user_subscription_id' => $subscription->id,
                        'order_id' => $order->id,
                        'requested_carrier_company_id' => (int) $resolvedCarrierId,
                        'consumed_carrier_company_id' => $consumedCarrierId,
                        'requested_price_per_shipment' => $requestedPrice,
                        'consumed_price_per_shipment' => $consumedPrice,
                        'price_difference' => round($consumedPrice - $requestedPrice, 2),
                        'settlement_status' => 'pending',
                        'notes' => 'تحويل تلقائي لعدم توفر رصيد في الناقل المطلوب.',
                    ]);
                }
            }

            $subscription->deductShipment();
            $order->update([
                'subscription_deducted_at' => now(),
                'consumed_carrier_company_id' => $consumedCarrierId,
            ]);
        });

        return [
            'success' => true,
            'message' => 'تم خصم شحنة من رصيد الاشتراك عند بدء الاستلام.',
            'subscription' => $subscription->fresh(),
        ];
    }

    public function getSubscriptionOrderStats(UserSubscription $subscription): array
    {
        $baseQuery = Order::query()->where('user_subscription_id', $subscription->id);

        $deliveredCount = (clone $baseQuery)
            ->join('tracking_statuses', 'tracking_statuses.id', '=', 'orders.order_status_id')
            ->where('tracking_statuses.code', 'DELIVERED')
            ->count();

        $cancelledCount = (clone $baseQuery)
            ->join('tracking_statuses', 'tracking_statuses.id', '=', 'orders.order_status_id')
            ->where('tracking_statuses.code', 'CANCELLED')
            ->count();

        $returnedCount = (clone $baseQuery)
            ->join('tracking_statuses', 'tracking_statuses.id', '=', 'orders.order_status_id')
            ->where('tracking_statuses.code', 'RETURNED_TO_SENDER')
            ->count();

        $pickedUpDeducted = (clone $baseQuery)
            ->whereNotNull('subscription_deducted_at')
            ->count();

        return [
            'package_total_shipments' => (int) ($subscription->order_limit ?? 0),
            'requests_delivered_counted' => $deliveredCount,
            'requests_pickedup_deducted' => $pickedUpDeducted,
            'requests_cancelled' => $cancelledCount,
            'requests_returned' => $returnedCount,
            'remaining_shipments' => (int) ($subscription->remaining_shipments ?? 0),
            'used_shipments' => (int) ($subscription->used_shipments ?? 0),
        ];
    }

    public function validateCouponForPlan(string $couponCode, int $planId, int $userId, float $totalBeforeCoupon): array
    {
        $code = strtoupper(trim($couponCode));

        if ($code === '') {
            return [
                'valid' => false,
                'message' => 'كوبون الخصم غير صالح.',
            ];
        }

        $coupon = SubscriptionCoupon::query()
            ->whereRaw('UPPER(code) = ?', [$code])
            ->where('is_active', true)
            ->first();

        if (!$coupon) {
            return [
                'valid' => false,
                'message' => 'الكوبون غير موجود أو غير مفعل.',
            ];
        }

        $now = now();
        if ($coupon->starts_at && $now->lt($coupon->starts_at)) {
            return [
                'valid' => false,
                'message' => 'الكوبون لم يبدأ بعد.',
            ];
        }

        if ($coupon->ends_at && $now->gt($coupon->ends_at)) {
            return [
                'valid' => false,
                'message' => 'انتهت صلاحية الكوبون.',
            ];
        }

        if ($coupon->usage_limit !== null && (int) $coupon->used_count >= (int) $coupon->usage_limit) {
            return [
                'valid' => false,
                'message' => 'تم استهلاك الكوبون بالكامل.',
            ];
        }

        if ($coupon->per_user_limit !== null) {
            $userUsageCount = SubscriptionCouponUsage::query()
                ->where('coupon_id', $coupon->id)
                ->where('user_id', $userId)
                ->count();

            if ($userUsageCount >= (int) $coupon->per_user_limit) {
                return [
                    'valid' => false,
                    'message' => 'تم تجاوز الحد المسموح لاستخدام الكوبون لهذا العميل.',
                ];
            }
        }

        $applicablePlanIds = (array) ($coupon->applicable_plan_ids ?? []);
        if (!empty($applicablePlanIds) && !in_array((int) $planId, array_map('intval', $applicablePlanIds), true)) {
            return [
                'valid' => false,
                'message' => 'الكوبون غير متاح لهذه الباقة.',
            ];
        }

        if ($totalBeforeCoupon < (float) $coupon->min_order_amount) {
            return [
                'valid' => false,
                'message' => 'قيمة الاشتراك أقل من الحد الأدنى لتفعيل الكوبون.',
            ];
        }

        $discountAmount = 0.0;
        if ($coupon->discount_type === 'percent') {
            $discountAmount = round($totalBeforeCoupon * ((float) $coupon->discount_value / 100), 2);
            if ($coupon->max_discount_amount !== null) {
                $discountAmount = min($discountAmount, (float) $coupon->max_discount_amount);
            }
        } else {
            $discountAmount = round((float) $coupon->discount_value, 2);
        }

        $discountAmount = min($discountAmount, max(0, $totalBeforeCoupon));
        $totalAfterCoupon = round(max(0, $totalBeforeCoupon - $discountAmount), 2);

        if ($discountAmount <= 0) {
            return [
                'valid' => false,
                'message' => 'الكوبون لا يطبق خصماً على هذه العملية.',
            ];
        }

        return [
            'valid' => true,
            'coupon' => $coupon,
            'discount_amount' => $discountAmount,
            'total_before_coupon' => round($totalBeforeCoupon, 2),
            'total_after_coupon' => $totalAfterCoupon,
        ];
    }

    private function applyCouponOrFail(?string $couponCode, int $planId, int $userId, float $totalBeforeCoupon): array
    {
        if (!$couponCode || trim($couponCode) === '') {
            return [
                'coupon' => null,
                'coupon_code' => null,
                'coupon_discount_type' => null,
                'coupon_discount_value' => 0,
                'coupon_discount_amount' => 0,
                'total_before_coupon' => round($totalBeforeCoupon, 2),
                'total_after_coupon' => round($totalBeforeCoupon, 2),
            ];
        }

        $result = $this->validateCouponForPlan($couponCode, $planId, $userId, $totalBeforeCoupon);
        if (empty($result['valid'])) {
            throw new \InvalidArgumentException((string) ($result['message'] ?? 'الكوبون غير صالح.'));
        }

        /** @var SubscriptionCoupon $coupon */
        $coupon = $result['coupon'];

        return [
            'coupon' => $coupon,
            'coupon_code' => $coupon->code,
            'coupon_discount_type' => $coupon->discount_type,
            'coupon_discount_value' => (float) $coupon->discount_value,
            'coupon_discount_amount' => (float) $result['discount_amount'],
            'total_before_coupon' => (float) $result['total_before_coupon'],
            'total_after_coupon' => (float) $result['total_after_coupon'],
        ];
    }

    private function recordCouponUsage(SubscriptionCoupon $coupon, int $userId, int $planId, int $userSubscriptionId, float $discountAmount, float $before, float $after): void
    {
        SubscriptionCouponUsage::create([
            'coupon_id' => $coupon->id,
            'user_id' => $userId,
            'plan_id' => $planId,
            'user_subscription_id' => $userSubscriptionId,
            'discount_amount' => $discountAmount,
            'total_before_discount' => $before,
            'total_after_discount' => $after,
            'used_at' => now(),
        ]);

        $coupon->increment('used_count');
    }

    /**
     * إعدادات ضريبة الاشتراكات (إن وجدت)
     */
    public function getSubscriptionTaxSetting(): ?TaxSetting
    {
        return TaxSetting::query()
            ->where('title_en', 'subscription_tax')
            ->latest('id')
            ->first()
            ?? TaxSetting::query()
                ->where('status', '1')
                ->latest('id')
                ->first();
    }

    /**
     * حساب التسعير الإجمالي لخطة اشتراك محفوظة
     */
    public function calculatePlanPricing(SubscriptionsPlan $plan): array
    {
        $shipmentsTotal = (float) ($plan->shipments_price_total
            ?? ((float) $plan->orders_count * (float) ($plan->base_shipment_price ?? $plan->order_price ?? 0)));

        $paidServicesTotal = (float) $plan->features()
            ->where('feature_type', 'paid')
            ->where('is_included', false)
            ->sum('extra_cost');

        return $this->buildPricingBreakdown($shipmentsTotal, $paidServicesTotal);
    }

    /**
     * حساب التسعير أثناء إنشاء/تعديل الخطة من بيانات الفورم
     */
    public function calculatePlanPricingFromPayload(int $ordersCount, float $baseShipmentPrice, array $features = []): array
    {
        $shipmentsTotal = round($ordersCount * $baseShipmentPrice, 2);

        $paidServicesTotal = 0;
        foreach ($features as $feature) {
            $type = (string) ($feature['feature_type'] ?? 'basic');
            $isIncluded = !empty($feature['is_included']);
            $extraCost = (float) ($feature['extra_cost'] ?? 0);

            if ($type === 'paid' && !$isIncluded && $extraCost > 0) {
                $paidServicesTotal += $extraCost;
            }
        }

        return $this->buildPricingBreakdown($shipmentsTotal, $paidServicesTotal);
    }

    /**
     * مُجمّع حسابات الفاتورة: الشحنات + الخدمات المدفوعة + ضريبة (اختياري)
     */
    public function buildPricingBreakdown(float $shipmentsTotal, float $paidServicesTotal): array
    {
        $taxSetting = $this->getSubscriptionTaxSetting();
        $subtotal = round($shipmentsTotal + $paidServicesTotal, 2);

        $taxEnabled = $taxSetting && (string) $taxSetting->status === '1';
        $taxType = $taxSetting?->tax_type;
        $taxRate = (float) ($taxSetting?->tax_value ?? 0);

        $taxAmount = 0.0;
        if ($taxEnabled && $taxRate > 0) {
            if ((string) $taxType === '1') {
                $taxAmount = round($subtotal * ($taxRate / 100), 2);
            } else {
                $taxAmount = round($taxRate, 2);
            }
        }

        $total = round($subtotal + $taxAmount, 2);

        return [
            'shipments_price_total' => round($shipmentsTotal, 2),
            'paid_services_price_total' => round($paidServicesTotal, 2),
            'subtotal_before_tax' => $subtotal,
            'tax_enabled' => $taxEnabled,
            'tax_type' => $taxType,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total_price' => $total,
        ];
    }

    // =========================================================
    // 1. جلب الاشتراك الفعّال للمستخدم
    // =========================================================

    /**
     * يعيد الاشتراك الفعّال الحالي للمستخدم أو null
     */
    public function getActiveSubscription(int $userId): ?UserSubscription
    {
        return UserSubscription::where('user_id', $userId)
            ->where('subscription_status', 'active')
            ->where(function ($q) {
                $q->whereNull('expiry_date')
                  ->orWhere('expiry_date', '>=', now()->toDateString());
            })
            ->where('remaining_shipments', '>', 0)
            ->latest('start_date')
            ->first();
    }

    /**
     * يعيد آخر اشتراك للمستخدم بصرف النظر عن حالته
     */
    public function getLatestSubscription(int $userId): ?UserSubscription
    {
        return UserSubscription::where('user_id', $userId)
            ->latest()
            ->first();
    }

    // =========================================================
    // 2. ربط طلب بالاشتراك وخصم الشحنة
    // =========================================================

    /**
     * ربط الطلب بالاشتراك الفعّال وخصم شحنة
     * يُستدعى عند إنشاء طلب جديد
     *
     * @return array ['success'=>bool, 'message'=>string, 'subscription'=>UserSubscription|null]
     */
    public function attachOrderToSubscription(Order $order, int $userId): array
    {
        $subscription = $this->getActiveSubscription($userId);

        if (!$subscription) {
            // محاولة استرداد اشتراك منتهي الشحنات أو منتهي الصلاحية
            $last = $this->getLatestSubscription($userId);
            if (!$last) {
                return [
                    'success'      => false,
                    'message'      => 'لا يوجد اشتراك فعّال. يرجى شراء باقة اشتراك أولاً.',
                    'subscription' => null,
                ];
            }
            if ($last->isExhausted()) {
                return [
                    'success'      => false,
                    'message'      => 'استنفذت جميع شحنات الباقة. يرجى تجديد الاشتراك.',
                    'subscription' => $last,
                ];
            }
            if ($last->isExpired()) {
                return [
                    'success'      => false,
                    'message'      => 'انتهت صلاحية الاشتراك. يرجى تجديد الاشتراك.',
                    'subscription' => $last,
                ];
            }
            return [
                'success'      => false,
                'message'      => 'لا يوجد اشتراك فعّال.',
                'subscription' => null,
            ];
        }

        DB::transaction(function () use ($order, $subscription) {
            // ربط الطلب بالاشتراك
            $order->update(['user_subscription_id' => $subscription->id]);
            // خصم شحنة
            $subscription->deductShipment();
        });

        return [
            'success'      => true,
            'message'      => 'تم ربط الطلب بالاشتراك بنجاح.',
            'subscription' => $subscription->fresh(),
        ];
    }

    /**
     * إلغاء ارتباط طلب بالاشتراك وإعادة الشحنة
     */
    public function detachOrderFromSubscription(Order $order): void
    {
        if ($order->user_subscription_id) {
            $subscription = $order->userSubscription;
            if ($subscription) {
                $subscription->refundShipment();
            }
            $order->update(['user_subscription_id' => null]);
        }
    }

    // =========================================================
    // 3. إنشاء اشتراك جديد بعد الدفع
    // =========================================================

    /**
     * إنشاء اشتراك جديد للمستخدم بعد تأكيد الدفع
     */
    public function createSubscription(
        int $userId,
        int $planId,
        string $paymentReference = '',
        ?float $paidAmount = null,
        ?string $couponCode = null
    ): UserSubscription {
        $location = $this->resolveUserLocationForPolicies($userId);
        app(LocationActivationService::class)->assertSubscriptionAllowed(
            $location['city_id'],
            $location['neighborhood_id']
        );

        $plan = SubscriptionsPlan::findOrFail($planId);
        $pricing = $this->calculatePlanPricing($plan);

        return DB::transaction(function () use ($userId, $plan, $paymentReference, $paidAmount, $pricing, $couponCode) {
            $startDate  = Carbon::today();
            $expiryDate = $startDate->copy()->addDays($plan->validity_days ?? 30);

            $couponSnapshot = $this->applyCouponOrFail(
                couponCode: $couponCode,
                planId: (int) $plan->id,
                userId: $userId,
                totalBeforeCoupon: (float) ($pricing['total_price'] ?? 0)
            );
            $finalTotal = (float) $couponSnapshot['total_after_coupon'];

            $userSub = UserSubscription::create([
                'user_id'              => $userId,
                'subscription_id'      => $plan->id,
                'monthly_price'        => $plan->m_price ?? 0,
                'paid_amount'          => $paidAmount ?? $finalTotal,
                'discount'             => (float) $couponSnapshot['coupon_discount_amount'],
                'order_limit'          => $plan->orders_count,
                'used_shipments'       => 0,
                'remaining_shipments'  => $plan->orders_count,
                'start_date'           => $startDate->format('Y-m-d'),
                'end_date'             => $expiryDate->format('Y-m-d'),
                'expiry_date'          => $expiryDate->format('Y-m-d'),
                'status'               => '0',
                'subscription_status'  => 'active',
                'renewal_count'        => 0,
                'cod_fees_prepaid'     => $plan->cod_fees_prepaid,
                'bank_fees_prepaid'    => $plan->bank_fees_prepaid,
                'payment_reference'    => $paymentReference,
                'coupon_code'          => $couponSnapshot['coupon_code'],
                'coupon_discount_type' => $couponSnapshot['coupon_discount_type'],
                'coupon_discount_value' => $couponSnapshot['coupon_discount_value'],
                'coupon_discount_amount' => $couponSnapshot['coupon_discount_amount'],
                'total_before_coupon' => $couponSnapshot['total_before_coupon'],
                'total_after_coupon' => $couponSnapshot['total_after_coupon'],
                'forfeited_amount'     => 0,
                'shipments_price_total' => $pricing['shipments_price_total'],
                'paid_services_price_total' => $pricing['paid_services_price_total'],
                'subtotal_before_tax' => $pricing['subtotal_before_tax'],
                'tax_enabled' => $pricing['tax_enabled'],
                'tax_type' => $pricing['tax_type'],
                'tax_rate' => $pricing['tax_rate'],
                'tax_amount' => $pricing['tax_amount'],
                'total_price' => $finalTotal,
            ]);

            $this->initializeCarrierBalances($userSub, $plan);

            if ($couponSnapshot['coupon'] instanceof SubscriptionCoupon) {
                $this->recordCouponUsage(
                    coupon: $couponSnapshot['coupon'],
                    userId: $userId,
                    planId: (int) $plan->id,
                    userSubscriptionId: (int) $userSub->id,
                    discountAmount: (float) $couponSnapshot['coupon_discount_amount'],
                    before: (float) $couponSnapshot['total_before_coupon'],
                    after: (float) $couponSnapshot['total_after_coupon']
                );
            }

            // تسجيل التجديد
            SubscriptionRenewal::create([
                'user_subscription_id'     => $userSub->id,
                'user_id'                  => $userId,
                'plan_id'                  => $plan->id,
                'renewal_type'             => 'new',
                'shipments_added'          => $plan->orders_count,
                'shipments_before'         => 0,
                'previous_expiry'          => null,
                'new_expiry'               => $expiryDate->format('Y-m-d'),
                'amount_paid'              => $paidAmount ?? $finalTotal,
                'forfeited_from_previous'  => 0,
                'payment_reference'        => $paymentReference,
            ]);

            // تحديث الاشتراك الفعّال على المستخدم
            \App\Models\User::where('id', $userId)
                ->update(['active_user_subscription_id' => $userSub->id]);

            app(CentralFinanceBillingService::class)
                ->createOrUpdateSubscriptionInvoice($userSub, 'new');

            return $userSub;
        });
    }

    // =========================================================
    // 4. تجديد الاشتراك
    // =========================================================

    /**
     * تجديد الاشتراك (نفس الخطة أو خطة مختلفة)
     * - في حال التجديد قبل انتهاء الصلاحية: يذهب الرصيد المتبقي
     * - يُحسب forfeited_amount بناءً على قيمة الشحنات المتبقية
     */
    public function renewSubscription(
        int $userId,
        int $planId,
        string $renewalType = 'renewal',
        string $paymentReference = '',
        ?float $paidAmount = null,
        ?int $adminId = null,
        ?string $couponCode = null
    ): UserSubscription {
        $location = $this->resolveUserLocationForPolicies($userId);
        app(LocationActivationService::class)->assertSubscriptionAllowed(
            $location['city_id'],
            $location['neighborhood_id']
        );

        $plan    = SubscriptionsPlan::findOrFail($planId);
        $pricing = $this->calculatePlanPricing($plan);
        $current = $this->getLatestSubscription($userId);

        return DB::transaction(function () use ($userId, $plan, $current, $renewalType, $paymentReference, $paidAmount, $adminId, $pricing, $couponCode) {
            $previousExpiry     = null;
            $forfeitedAmount    = 0;
            $shipmentsBefore    = 0;

            if ($current) {
                // حساب الرصيد المُهدر من الاشتراك السابق
                $previousExpiry  = $current->expiry_date;
                $shipmentsBefore = $current->remaining_shipments;

                // قيمة كل شحنة = السعر المدفوع ÷ إجمالي الشحنات
                $pricePerShipment = $current->order_limit > 0
                    ? ($current->paid_amount / $current->order_limit)
                    : 0;

                $forfeitedAmount = round($pricePerShipment * $shipmentsBefore, 2);

                // إنهاء الاشتراك القديم
                $current->update([
                    'subscription_status' => 'cancelled',
                    'forfeited_amount'    => $forfeitedAmount,
                ]);
            }

            $startDate  = Carbon::today();
            $expiryDate = $startDate->copy()->addDays($plan->validity_days ?? 30);

            $couponSnapshot = $this->applyCouponOrFail(
                couponCode: $couponCode,
                planId: (int) $plan->id,
                userId: $userId,
                totalBeforeCoupon: (float) ($pricing['total_price'] ?? 0)
            );
            $finalTotal = (float) $couponSnapshot['total_after_coupon'];

            $newSub = UserSubscription::create([
                'user_id'              => $userId,
                'subscription_id'      => $plan->id,
                'monthly_price'        => $plan->m_price ?? 0,
                'paid_amount'          => $paidAmount ?? $finalTotal,
                'discount'             => (float) $couponSnapshot['coupon_discount_amount'],
                'order_limit'          => $plan->orders_count,
                'used_shipments'       => 0,
                'remaining_shipments'  => $plan->orders_count,
                'start_date'           => $startDate->format('Y-m-d'),
                'end_date'             => $expiryDate->format('Y-m-d'),
                'expiry_date'          => $expiryDate->format('Y-m-d'),
                'status'               => '0',
                'subscription_status'  => 'active',
                'renewal_count'        => ($current ? $current->renewal_count + 1 : 1),
                'cod_fees_prepaid'     => $plan->cod_fees_prepaid,
                'bank_fees_prepaid'    => $plan->bank_fees_prepaid,
                'payment_reference'    => $paymentReference,
                'coupon_code'          => $couponSnapshot['coupon_code'],
                'coupon_discount_type' => $couponSnapshot['coupon_discount_type'],
                'coupon_discount_value' => $couponSnapshot['coupon_discount_value'],
                'coupon_discount_amount' => $couponSnapshot['coupon_discount_amount'],
                'total_before_coupon' => $couponSnapshot['total_before_coupon'],
                'total_after_coupon' => $couponSnapshot['total_after_coupon'],
                'forfeited_amount'     => 0,
                'shipments_price_total' => $pricing['shipments_price_total'],
                'paid_services_price_total' => $pricing['paid_services_price_total'],
                'subtotal_before_tax' => $pricing['subtotal_before_tax'],
                'tax_enabled' => $pricing['tax_enabled'],
                'tax_type' => $pricing['tax_type'],
                'tax_rate' => $pricing['tax_rate'],
                'tax_amount' => $pricing['tax_amount'],
                'total_price' => $finalTotal,
            ]);

            $this->initializeCarrierBalances($newSub, $plan);

            if ($couponSnapshot['coupon'] instanceof SubscriptionCoupon) {
                $this->recordCouponUsage(
                    coupon: $couponSnapshot['coupon'],
                    userId: $userId,
                    planId: (int) $plan->id,
                    userSubscriptionId: (int) $newSub->id,
                    discountAmount: (float) $couponSnapshot['coupon_discount_amount'],
                    before: (float) $couponSnapshot['total_before_coupon'],
                    after: (float) $couponSnapshot['total_after_coupon']
                );
            }

            SubscriptionRenewal::create([
                'user_subscription_id'     => $newSub->id,
                'user_id'                  => $userId,
                'plan_id'                  => $plan->id,
                'renewal_type'             => $renewalType,
                'shipments_added'          => $plan->orders_count,
                'shipments_before'         => $shipmentsBefore,
                'previous_expiry'          => $previousExpiry,
                'new_expiry'               => $expiryDate->format('Y-m-d'),
                'amount_paid'              => $paidAmount ?? $finalTotal,
                'forfeited_from_previous'  => $forfeitedAmount,
                'payment_reference'        => $paymentReference,
                'processed_by'             => $adminId,
            ]);

            \App\Models\User::where('id', $userId)
                ->update(['active_user_subscription_id' => $newSub->id]);

            app(CentralFinanceBillingService::class)
                ->createOrUpdateSubscriptionInvoice($newSub, $renewalType ?: 'renewal');

            return $newSub;
        });
    }

    /**
     * تمديد الاشتراك الحالي (زيادة مدة الصلاحية فقط - برسوم تمديد)
     */
    public function extendSubscription(
        UserSubscription $subscription,
        int $extraDays,
        float $extensionFee,
        string $paymentReference = '',
        ?int $adminId = null
    ): UserSubscription {
        return DB::transaction(function () use ($subscription, $extraDays, $extensionFee, $paymentReference, $adminId) {
            $currentExpiry = Carbon::parse($subscription->expiry_date ?? $subscription->end_date);
            $newExpiry     = $currentExpiry->addDays($extraDays);

            $subscription->update([
                'expiry_date' => $newExpiry->format('Y-m-d'),
                'end_date'    => $newExpiry->format('Y-m-d'),
                'subscription_status' => 'active',
            ]);

            $renewal = SubscriptionRenewal::create([
                'user_subscription_id'     => $subscription->id,
                'user_id'                  => $subscription->user_id,
                'plan_id'                  => $subscription->subscription_id,
                'renewal_type'             => 'extension',
                'shipments_added'          => 0,
                'shipments_before'         => $subscription->remaining_shipments,
                'previous_expiry'          => $currentExpiry->format('Y-m-d'),
                'new_expiry'               => $newExpiry->format('Y-m-d'),
                'amount_paid'              => $extensionFee,
                'forfeited_from_previous'  => 0,
                'payment_reference'        => $paymentReference,
                'processed_by'             => $adminId,
            ]);

            app(CentralFinanceBillingService::class)
                ->createSubscriptionExtensionInvoice($subscription, $extensionFee, (int) $renewal->id);

            return $subscription->fresh();
        });
    }

    // =========================================================
    // 5. فحص صلاحية الاشتراك وتحديث الحالات (Cron/Scheduler)
    // =========================================================

    /**
     * تحديث حالات الاشتراكات المنتهية الصلاحية
     * يُستدعى من Scheduler يومياً
     */
    public function expireOldSubscriptions(): int
    {
        return UserSubscription::where('subscription_status', 'active')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->toDateString())
            ->update(['subscription_status' => 'expired']);
    }

    // =========================================================
    // 6. حساب المقاصة مع COD
    // =========================================================

    /**
     * حساب تفاصيل المقاصة للعميل:
     * - هل رسوم COD مدفوعة من الاشتراك؟
     * - هل الرسوم البنكية مدفوعة من الاشتراك؟
     * - ما المبلغ الصافي المستحق للعميل؟
     *
     * @param  int    $userId
     * @param  float  $totalCodAmount    إجمالي مبالغ COD
     * @param  float  $shippingCostTotal إجمالي تكاليف الشحن المستحقة
     * @return array
     */
    public function calculateSettlementBreakdown(
        int $userId,
        float $totalCodAmount,
        float $shippingCostTotal = 0
    ): array {
        $subscription = $this->getActiveSubscription($userId)
                      ?? $this->getLatestSubscription($userId);

        $plan = $subscription?->subscription?->fresh();

        $codFeeRate  = $plan?->cod_fee_rate  ?? 0;
        $bankFeeRate = $plan?->bank_fee_rate ?? 0;

        $codPrepaid  = (bool) ($subscription?->cod_fees_prepaid  ?? false);
        $bankPrepaid = (bool) ($subscription?->bank_fees_prepaid ?? false);

        // رسوم COD
        $codFeeDeducted = $codPrepaid
            ? 0
            : round($totalCodAmount * ($codFeeRate / 100), 2);

        // الرسوم البنكية
        $bankFeeDeducted = $bankPrepaid
            ? 0
            : round($totalCodAmount * ($bankFeeRate / 100), 2);

        // رسوم الخدمات الإضافية المعلّقة
        $pendingExtras = SubscriptionExtraCharge::where('user_id', $userId)
            ->where('status', 'pending')
            ->where('payment_method', 'deduct_from_cod')
            ->sum('amount');

        $totalDeductions = $codFeeDeducted + $bankFeeDeducted + $pendingExtras;
        $netAmount       = round($totalCodAmount - $totalDeductions, 2);

        return [
            'user_id'                      => $userId,
            'subscription_id'              => $subscription?->id,
            'total_cod_amount'             => $totalCodAmount,
            'cod_prepaid_by_subscription'  => $codPrepaid,
            'bank_fees_prepaid'            => $bankPrepaid,
            'cod_fee_rate'                 => $codFeeRate,
            'bank_fee_rate'                => $bankFeeRate,
            'cod_fee_deducted'             => $codFeeDeducted,
            'bank_fee_deducted'            => $bankFeeDeducted,
            'extra_charges_deducted'       => $pendingExtras,
            'total_deductions'             => $totalDeductions,
            'net_amount_to_transfer'       => max(0, $netAmount),
        ];
    }

    /**
     * تسوية رسوم الخدمات الإضافية المربوطة بـ COD عند صرف المقاصة
     */
    public function settleExtraChargesFromCod(int $userId): float
    {
        $charges = SubscriptionExtraCharge::where('user_id', $userId)
            ->where('status', 'pending')
            ->where('payment_method', 'deduct_from_cod')
            ->get();

        $total = 0;
        foreach ($charges as $charge) {
            $charge->update(['status' => 'deducted']);
            $total += $charge->amount;
        }
        return $total;
    }

    // =========================================================
    // 7. إضافة رسوم خدمة إضافية للعميل
    // =========================================================

    public function addExtraCharge(
        int $userId,
        string $chargeType,
        float $amount,
        string $paymentMethod = 'deduct_from_cod',
        ?int $orderId = null,
        ?int $userSubscriptionId = null,
        string $description = ''
    ): SubscriptionExtraCharge {
        $cityId = null;
        $neighborhoodId = null;

        if ($orderId) {
            $order = Order::with(['sender', 'recipient'])->find($orderId);
            $cityId = $order?->sender?->city_id ?? $order?->recipient?->city_id;
            $neighborhoodId = $order?->sender?->neighborhood_id ?? $order?->recipient?->neighborhood_id;
        }

        if (!$cityId) {
            $location = $this->resolveUserLocationForPolicies($userId);
            $cityId = $location['city_id'];
            $neighborhoodId = $location['neighborhood_id'];
        }

        app(LocationActivationService::class)->assertExtraServicesAllowed($cityId, $neighborhoodId);

        $charge = SubscriptionExtraCharge::create([
            'user_id'              => $userId,
            'order_id'             => $orderId,
            'user_subscription_id' => $userSubscriptionId,
            'charge_type'          => $chargeType,
            'description_ar'       => $description,
            'amount'               => $amount,
            'payment_method'       => $paymentMethod,
            'status'               => 'pending',
        ]);

        app(CentralFinanceBillingService::class)
            ->createOrUpdateExtraChargeInvoice($charge);

        return $charge;
    }

    // =========================================================
    // 8. تحقق من توفر ميزة للمستخدم في اشتراكه
    // =========================================================

    /**
     * هل الميزة المطلوبة مشمولة مجاناً في باقة المستخدم؟
     */
    public function isFeatureIncluded(int $userId, string $featureKey): bool
    {
        $subscription = $this->getActiveSubscription($userId);
        if (!$subscription) return false;

        return $subscription->subscription
            ->features()
            ->where('feature_key', $featureKey)
            ->where('is_included', true)
            ->exists();
    }

    /**
     * جلب تكلفة ميزة إضافية لاشتراك معين
     */
    public function getFeatureExtraCost(int $userId, string $featureKey): float
    {
        $subscription = $this->getActiveSubscription($userId);
        if (!$subscription) return 0;

        $feature = $subscription->subscription
            ->features()
            ->where('feature_key', $featureKey)
            ->first();

        return $feature ? (float) $feature->extra_cost : 0;
    }

    private function initializeCarrierBalances(UserSubscription $userSubscription, SubscriptionsPlan $plan): void
    {
        $planCarrierRows = SubscriptionPlanCarrier::query()
            ->where('plan_id', $plan->id)
            ->where('is_active', true)
            ->get();

        if ($planCarrierRows->isEmpty()) {
            return;
        }

        $totalAllocated = (int) $planCarrierRows->sum('allocated_shipments');
        if ($totalAllocated > 0 && $totalAllocated !== (int) $userSubscription->order_limit) {
            $userSubscription->update([
                'order_limit' => $totalAllocated,
                'remaining_shipments' => $totalAllocated,
            ]);
        }

        foreach ($planCarrierRows as $row) {
            UserSubscriptionCarrierBalance::updateOrCreate(
                [
                    'user_subscription_id' => $userSubscription->id,
                    'carrier_company_id' => $row->carrier_company_id,
                ],
                [
                    'plan_carrier_id' => $row->id,
                    'allocated_shipments' => (int) $row->allocated_shipments,
                    'used_shipments' => 0,
                    'remaining_shipments' => (int) $row->allocated_shipments,
                    'price_per_shipment' => (float) $row->price_per_shipment,
                    'is_active' => true,
                ]
            );
        }
    }

    private function resolveOrderCarrierCompanyId(Order $order, ?int $trackingCarrierCompanyId = null): ?int
    {
        if ($trackingCarrierCompanyId && $trackingCarrierCompanyId > 0) {
            return $trackingCarrierCompanyId;
        }

        if ($order->relationLoaded('latestTracking') && $order->latestTracking?->carrier_company_id) {
            return (int) $order->latestTracking->carrier_company_id;
        }

        $latestTrackingCarrierId = $order->shipmentTracking()
            ->whereNotNull('carrier_company_id')
            ->latest('event_time')
            ->value('carrier_company_id');
        if ($latestTrackingCarrierId) {
            return (int) $latestTrackingCarrierId;
        }

        $latestWaybillCarrierId = $order->carrierWaybills()
            ->latest('id')
            ->value('carrier_company_id');

        return $latestWaybillCarrierId ? (int) $latestWaybillCarrierId : null;
    }

    private function resolveUserLocationForPolicies(int $userId): array
    {
        $address = Address::query()
            ->where('user_id', $userId)
            ->whereNotNull('city_id')
            ->orderByDesc('id')
            ->first();

        return [
            'city_id' => $address?->city_id,
            'neighborhood_id' => $address?->neighborhood_id,
        ];
    }
}
