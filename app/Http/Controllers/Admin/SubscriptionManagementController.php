<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionExtraCharge;
use App\Models\SubscriptionPlanFeature;
use App\Models\SubscriptionPlanRegion;
use App\Models\SubscriptionRegionSetting;
use App\Models\SubscriptionRenewal;
use App\Models\SubscriptionSettingOption;
use App\Models\SubscriptionsPlan;
use App\Models\SubscriptionsCategory;
use App\Models\SubscriptionPeriod;
use App\Models\SubscriptionCoupon;
use App\Models\CarrierCompany;
use App\Models\TaxSetting;
use App\Models\Region;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\CentralFinanceBillingService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionManagementController extends Controller
{
    public function __construct(protected SubscriptionService $service) {}

    private function ensureDefaultSettingOptions(): void
    {
        $defaultBusiness = [
            ['code' => '1', 'name_ar' => 'أفراد'],
            ['code' => '2', 'name_ar' => 'أعمال'],
            ['code' => '3', 'name_ar' => 'متاجر'],
            ['code' => '4', 'name_ar' => 'شركات'],
        ];

        $defaultStore = [
            ['code' => '0', 'name_ar' => 'بدون متجر'],
            ['code' => '1', 'name_ar' => 'متجر كبير'],
            ['code' => '2', 'name_ar' => 'متجر صغير'],
        ];

        $defaultFeatures = SubscriptionPlanFeature::FEATURE_KEYS;

        foreach ($defaultBusiness as $i => $item) {
            SubscriptionSettingOption::firstOrCreate(
                ['type' => 'business_type', 'code' => $item['code']],
                ['name_ar' => $item['name_ar'], 'name_en' => null, 'is_active' => true, 'sort_order' => $i]
            );
        }

        foreach ($defaultStore as $i => $item) {
            SubscriptionSettingOption::firstOrCreate(
                ['type' => 'store_type', 'code' => $item['code']],
                ['name_ar' => $item['name_ar'], 'name_en' => null, 'is_active' => true, 'sort_order' => $i]
            );
        }

        $idx = 0;
        foreach ($defaultFeatures as $code => $nameAr) {
            SubscriptionSettingOption::firstOrCreate(
                ['type' => 'feature_key', 'code' => $code],
                ['name_ar' => $nameAr, 'name_en' => null, 'is_active' => true, 'sort_order' => $idx++]
            );
        }
    }

    // =========================================================
    // خطط الاشتراك
    // =========================================================

    public function plansIndex(Request $request)
    {
        $plans = SubscriptionsPlan::with(['category', 'subscription_period', 'features'])
            ->when($request->search, fn ($q) =>
                $q->where('title_ar', 'like', "%{$request->search}%"))
            ->when($request->status !== null, fn ($q) =>
                $q->where('status', $request->status))
            ->orderBy('sort_order')->orderBy('id')
            ->paginate(20);

        return view('admin.subscriptions.plans.index', compact('plans'));
    }

    public function plansCreate()
    {
        $this->ensureDefaultSettingOptions();

        $categories = SubscriptionsCategory::where('status', '0')->get();
        $periods    = SubscriptionPeriod::where('status', '1')->get();

        $featureKeys = SubscriptionSettingOption::where('type', 'feature_key')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn ($o) => [$o->code => ($o->name_ar ?: $o->code)])
            ->toArray();

        if (empty($featureKeys)) {
            $featureKeys = SubscriptionPlanFeature::FEATURE_KEYS;
        }

        $businessTypes = SubscriptionSettingOption::where('type', 'business_type')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $storeTypes = SubscriptionSettingOption::where('type', 'store_type')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $enabledRegions = SubscriptionRegionSetting::with('region')
            ->where('is_enabled', true)
            ->get();
        $activeCarriers = CarrierCompany::query()->where('is_active', true)->orderBy('name_ar')->get();
        $subscriptionTaxSetting = TaxSetting::query()
            ->where('title_en', 'subscription_tax')
            ->latest('id')
            ->first();

        return view('admin.subscriptions.plans.create', compact(
            'categories',
            'periods',
            'featureKeys',
            'businessTypes',
            'storeTypes',
            'enabledRegions',
            'activeCarriers',
            'subscriptionTaxSetting'
        ));
    }

    public function plansStore(Request $request)
    {
        $request->validate([
            'title_ar'           => 'required|string|max:255',
            'title_en'           => 'nullable|string|max:255',
            'category_id'        => 'required|exists:subscriptions_categories,id',
            'subscription_period_id' => 'required|exists:subscription_periods,id',
            'orders_count'       => 'required|integer|min:1',
            'base_shipment_price'=> 'required|numeric|min:0',
            'm_price'            => 'nullable|numeric|min:0',
            'renewal_fee'        => 'nullable|numeric|min:0',
            'cod_fees_prepaid'   => 'boolean',
            'bank_fees_prepaid'  => 'boolean',
            'cod_fee_rate'       => 'nullable|numeric|min:0|max:100',
            'bank_fee_rate'      => 'nullable|numeric|min:0|max:100',
            'business_type'      => 'required',
            'store_type'         => 'required',
            'allow_cross_carrier_fallback' => 'nullable|boolean',
            'features'           => 'nullable|array',
            'region_pricing'     => 'nullable|array',
            'carrier_pricing'    => 'nullable|array',
            'offer_start_date'   => 'nullable|date',
            'offer_end_date'     => 'nullable|date|after_or_equal:offer_start_date',
        ]);

        DB::transaction(function () use ($request) {
            $period = SubscriptionPeriod::findOrFail((int) $request->subscription_period_id);
            $ordersCount = (int) $request->orders_count;
            $baseShipmentPrice = (float) $request->base_shipment_price;
            $pricing = $this->service->calculatePlanPricingFromPayload(
                $ordersCount,
                $baseShipmentPrice,
                (array) $request->input('features', [])
            );

            $plan = SubscriptionsPlan::create([
                'category_id'          => $request->category_id,
                'title_ar'             => $request->title_ar,
                'title_en'             => $request->title_en,
                'm_price'              => $request->m_price ?? 0,
                'subscription_price'   => $pricing['total_price'],
                'subscription_period_id' => $request->subscription_period_id,
                'orders_count'         => $ordersCount,
                'order_price'          => $baseShipmentPrice,
                'base_shipment_price'  => $baseShipmentPrice,
                'shipments_price_total'=> $pricing['shipments_price_total'],
                'paid_services_price_total' => $pricing['paid_services_price_total'],
                'subtotal_before_tax'  => $pricing['subtotal_before_tax'],
                'tax_enabled'          => $pricing['tax_enabled'],
                'tax_type'             => $pricing['tax_type'],
                'tax_rate'             => $pricing['tax_rate'],
                'tax_amount'           => $pricing['tax_amount'],
                'total_price'          => $pricing['total_price'],
                'validity_days'        => (int) $period->period,
                'renewal_fee'          => $request->renewal_fee ?? 0,
                'cod_fees_prepaid'     => $request->boolean('cod_fees_prepaid'),
                'bank_fees_prepaid'    => $request->boolean('bank_fees_prepaid'),
                'cod_fee_rate'         => $request->cod_fee_rate ?? 0,
                'bank_fee_rate'        => $request->bank_fee_rate ?? 0,
                'auto_renew'           => $request->boolean('auto_renew'),
                'max_renewals'         => $request->max_renewals,
                'business_type'        => $request->business_type,
                'store_type'           => $request->store_type,
                'short_desc_ar'        => $request->short_desc_ar,
                'description_ar'       => $request->description_ar,
                'description_en'       => $request->description_en,
                'status'               => $request->status ?? '0',
                'is_visible'           => $request->boolean('is_visible', true),
                'sort_order'           => $request->sort_order ?? 0,
                'offer_start_date'     => $request->offer_start_date,
                'offer_end_date'       => $request->offer_end_date,
                'is_offer_active'      => $request->boolean('is_offer_active'),
                'allow_cross_carrier_fallback' => $request->boolean('allow_cross_carrier_fallback', true),
            ]);

            // حفظ الميزات
            if ($request->features) {
                $featureNameMap = SubscriptionSettingOption::where('type', 'feature_key')
                    ->pluck('name_ar', 'code')
                    ->toArray();

                foreach ($request->features as $i => $feature) {
                    if (empty($feature['feature_key'])) continue;

                    $key = (string) $feature['feature_key'];
                    $nameAr = $feature['feature_name_ar']
                        ?? ($featureNameMap[$key] ?? (SubscriptionPlanFeature::FEATURE_KEYS[$key] ?? $key));

                    SubscriptionPlanFeature::create([
                        'plan_id'          => $plan->id,
                        'feature_key'      => $key,
                        'feature_name_ar'  => $nameAr,
                        'feature_name_en'  => $feature['feature_name_en'] ?? null,
                        'feature_type'     => $feature['feature_type'] ?? 'basic',
                        'is_included'      => array_key_exists('is_included', $feature),
                        'extra_cost'       => $feature['extra_cost'] ?? 0,
                        'unit'             => $feature['unit'] ?? null,
                        'sort_order'       => $i,
                    ]);
                }
            }

            // ربط الخطة بالمناطق المتاحة وأسعارها
            if ($request->filled('region_pricing')) {
                foreach ($request->region_pricing as $regionId => $config) {
                    if (empty($config['enabled'])) {
                        continue;
                    }

                    SubscriptionPlanRegion::create([
                        'plan_id'            => $plan->id,
                        'region_id'          => (int) $regionId,
                        'price_per_shipment' => (float) ($config['price_per_shipment'] ?? 0),
                        'is_active'          => true,
                    ]);
                }
            }

            $plan->carrierPricing()->delete();
            foreach ((array) $request->input('carrier_pricing', []) as $carrierId => $config) {
                if (empty($config['enabled'])) {
                    continue;
                }

                $plan->carrierPricing()->create([
                    'carrier_company_id' => (int) $carrierId,
                    'allocated_shipments' => max(0, (int) ($config['allocated_shipments'] ?? 0)),
                    'price_per_shipment' => max(0, (float) ($config['price_per_shipment'] ?? 0)),
                    'is_active' => true,
                ]);
            }
        });

        return redirect()->route('admin.subscriptions.plans.index')
            ->with('success', 'تم إنشاء خطة الاشتراك بنجاح.');
    }

    public function plansEdit(SubscriptionsPlan $plan)
    {
        $this->ensureDefaultSettingOptions();

        $plan->load(['features', 'regions', 'carrierPricing']);
        $categories  = SubscriptionsCategory::where('status', '0')->get();
        $periods     = SubscriptionPeriod::where('status', '1')->get();

        $featureKeys = SubscriptionSettingOption::where('type', 'feature_key')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn ($o) => [$o->code => ($o->name_ar ?: $o->code)])
            ->toArray();

        if (empty($featureKeys)) {
            $featureKeys = SubscriptionPlanFeature::FEATURE_KEYS;
        }

        $businessTypes = SubscriptionSettingOption::where('type', 'business_type')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $storeTypes = SubscriptionSettingOption::where('type', 'store_type')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $enabledRegions = SubscriptionRegionSetting::with('region')
            ->where('is_enabled', true)
            ->get();
        $activeCarriers = CarrierCompany::query()->where('is_active', true)->orderBy('name_ar')->get();
        $subscriptionTaxSetting = TaxSetting::query()
            ->where('title_en', 'subscription_tax')
            ->latest('id')
            ->first();

        $planRegionPricing = $plan->regions
            ->keyBy('region_id')
            ->map(fn ($r) => (float) $r->price_per_shipment)
            ->toArray();
        $planCarrierPricing = $plan->carrierPricing
            ->keyBy('carrier_company_id')
            ->map(fn ($c) => [
                'allocated_shipments' => (int) $c->allocated_shipments,
                'price_per_shipment' => (float) $c->price_per_shipment,
            ])
            ->toArray();

        return view('admin.subscriptions.plans.edit', compact(
            'plan',
            'categories',
            'periods',
            'featureKeys',
            'businessTypes',
            'storeTypes',
            'enabledRegions',
            'activeCarriers',
            'planRegionPricing',
            'planCarrierPricing',
            'subscriptionTaxSetting'
        ));
    }

    public function plansUpdate(Request $request, SubscriptionsPlan $plan)
    {
        $request->validate([
            'title_ar'           => 'required|string|max:255',
            'orders_count'       => 'required|integer|min:1',
            'base_shipment_price'=> 'required|numeric|min:0',
            'subscription_period_id' => 'required|exists:subscription_periods,id',
            'region_pricing'     => 'nullable|array',
            'carrier_pricing'    => 'nullable|array',
            'allow_cross_carrier_fallback' => 'nullable|boolean',
            'offer_start_date'   => 'nullable|date',
            'offer_end_date'     => 'nullable|date|after_or_equal:offer_start_date',
        ]);

        DB::transaction(function () use ($request, $plan) {
            $period = SubscriptionPeriod::findOrFail((int) $request->subscription_period_id);
            $ordersCount = (int) $request->orders_count;
            $baseShipmentPrice = (float) $request->base_shipment_price;
            $pricing = $this->service->calculatePlanPricingFromPayload(
                $ordersCount,
                $baseShipmentPrice,
                (array) $request->input('features', [])
            );

            $plan->update([
                'title_ar'             => $request->title_ar,
                'title_en'             => $request->title_en,
                'm_price'              => $request->m_price ?? 0,
                'subscription_price'   => $pricing['total_price'],
                'subscription_period_id' => $request->subscription_period_id,
                'orders_count'         => $ordersCount,
                'order_price'          => $baseShipmentPrice,
                'base_shipment_price'  => $baseShipmentPrice,
                'shipments_price_total'=> $pricing['shipments_price_total'],
                'paid_services_price_total' => $pricing['paid_services_price_total'],
                'subtotal_before_tax'  => $pricing['subtotal_before_tax'],
                'tax_enabled'          => $pricing['tax_enabled'],
                'tax_type'             => $pricing['tax_type'],
                'tax_rate'             => $pricing['tax_rate'],
                'tax_amount'           => $pricing['tax_amount'],
                'total_price'          => $pricing['total_price'],
                'validity_days'        => (int) $period->period,
                'renewal_fee'          => $request->renewal_fee ?? 0,
                'cod_fees_prepaid'     => $request->boolean('cod_fees_prepaid'),
                'bank_fees_prepaid'    => $request->boolean('bank_fees_prepaid'),
                'cod_fee_rate'         => $request->cod_fee_rate ?? 0,
                'bank_fee_rate'        => $request->bank_fee_rate ?? 0,
                'auto_renew'           => $request->boolean('auto_renew'),
                'max_renewals'         => $request->max_renewals,
                'business_type'        => $request->business_type,
                'store_type'           => $request->store_type,
                'short_desc_ar'        => $request->short_desc_ar,
                'description_ar'       => $request->description_ar,
                'description_en'       => $request->description_en,
                'status'               => $request->status ?? '0',
                'is_visible'           => $request->boolean('is_visible', true),
                'sort_order'           => $request->sort_order ?? 0,
                'offer_start_date'     => $request->offer_start_date,
                'offer_end_date'       => $request->offer_end_date,
                'is_offer_active'      => $request->boolean('is_offer_active'),
                'allow_cross_carrier_fallback' => $request->boolean('allow_cross_carrier_fallback', true),
            ]);

            // حذف الميزات القديمة وإعادة الإنشاء
            $plan->features()->delete();
            if ($request->features) {
                $featureNameMap = SubscriptionSettingOption::where('type', 'feature_key')
                    ->pluck('name_ar', 'code')
                    ->toArray();

                foreach ($request->features as $i => $feature) {
                    if (empty($feature['feature_key'])) continue;

                    $key = (string) $feature['feature_key'];
                    $nameAr = $feature['feature_name_ar']
                        ?? ($featureNameMap[$key] ?? (SubscriptionPlanFeature::FEATURE_KEYS[$key] ?? $key));

                    SubscriptionPlanFeature::create([
                        'plan_id'         => $plan->id,
                        'feature_key'     => $key,
                        'feature_name_ar' => $nameAr,
                        'feature_name_en' => $feature['feature_name_en'] ?? null,
                        'feature_type'    => $feature['feature_type'] ?? 'basic',
                        'is_included'     => array_key_exists('is_included', $feature),
                        'extra_cost'      => $feature['extra_cost'] ?? 0,
                        'unit'            => $feature['unit'] ?? null,
                        'sort_order'      => $i,
                    ]);
                }
            }

            $plan->regions()->delete();
            if ($request->filled('region_pricing')) {
                foreach ($request->region_pricing as $regionId => $config) {
                    if (empty($config['enabled'])) {
                        continue;
                    }

                    SubscriptionPlanRegion::create([
                        'plan_id'            => $plan->id,
                        'region_id'          => (int) $regionId,
                        'price_per_shipment' => (float) ($config['price_per_shipment'] ?? 0),
                        'is_active'          => true,
                    ]);
                }
            }

            $plan->carrierPricing()->delete();
            foreach ((array) $request->input('carrier_pricing', []) as $carrierId => $config) {
                if (empty($config['enabled'])) {
                    continue;
                }

                $plan->carrierPricing()->create([
                    'carrier_company_id' => (int) $carrierId,
                    'allocated_shipments' => max(0, (int) ($config['allocated_shipments'] ?? 0)),
                    'price_per_shipment' => max(0, (float) ($config['price_per_shipment'] ?? 0)),
                    'is_active' => true,
                ]);
            }
        });

        return redirect()->route('admin.subscriptions.plans.index')
            ->with('success', 'تم تحديث الخطة بنجاح.');
    }

    public function plansShow(SubscriptionsPlan $plan)
    {
        $plan->load(['features', 'subscription_period', 'category',
                     'subscriptionUserSubscriptions' => fn ($q) => $q->latest()->limit(10)]);

        $stats = [
            'total_subscribers'  => UserSubscription::where('subscription_id', $plan->id)->count(),
            'active_subscribers' => UserSubscription::where('subscription_id', $plan->id)
                ->where('subscription_status', 'active')->count(),
            'total_revenue'      => UserSubscription::where('subscription_id', $plan->id)->sum('paid_amount'),
        ];

        return view('admin.subscriptions.plans.show', compact('plan', 'stats'));
    }

    // =========================================================
    // اشتراكات المستخدمين
    // =========================================================

    public function subscriptionsIndex(Request $request)
    {
        $subs = UserSubscription::with(['user', 'subscription'])
            ->when($request->user_id, fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->status,  fn ($q) => $q->where('subscription_status', $request->status))
            ->when($request->plan_id, fn ($q) => $q->where('subscription_id', $request->plan_id))
            ->latest()
            ->paginate(25);

        $plans = SubscriptionsPlan::where('status', '0')->get();

        return view('admin.subscriptions.user-subscriptions.index', compact('subs', 'plans'));
    }

    public function subscriptionsShow(UserSubscription $userSubscription)
    {
        $userSubscription->load(['user', 'subscription.features', 'renewals.plan',
                                 'extraCharges',
                                 'carrierBalances.carrierCompany',
                                 'carrierSettlements.requestedCarrier',
                                 'carrierSettlements.consumedCarrier',
                                 'orders' => fn ($q) => $q->latest()->limit(20)]);
        $orderStats = $this->service->getSubscriptionOrderStats($userSubscription);

        return view('admin.subscriptions.user-subscriptions.show', compact('userSubscription', 'orderStats'));
    }

    public function subscriptionsInvoice(UserSubscription $userSubscription)
    {
        $userSubscription->load(['user', 'subscription.features']);

        $pricing = [
            'shipments_price_total' => (float) ($userSubscription->shipments_price_total ?? 0),
            'paid_services_price_total' => (float) ($userSubscription->paid_services_price_total ?? 0),
            'subtotal_before_tax' => (float) ($userSubscription->subtotal_before_tax ?? 0),
            'tax_enabled' => (bool) ($userSubscription->tax_enabled ?? false),
            'tax_type' => $userSubscription->tax_type,
            'tax_rate' => (float) ($userSubscription->tax_rate ?? 0),
            'tax_amount' => (float) ($userSubscription->tax_amount ?? 0),
            'total_price' => (float) ($userSubscription->total_price ?? $userSubscription->paid_amount ?? 0),
        ];

        if ($pricing['subtotal_before_tax'] <= 0 && $userSubscription->subscription) {
            $pricing = $this->service->calculatePlanPricing($userSubscription->subscription);
            $pricing['total_price'] = (float) ($userSubscription->paid_amount ?? $pricing['total_price']);
        }

        return view('admin.subscriptions.user-subscriptions.invoice', compact('userSubscription', 'pricing'));
    }

    /**
     * تجديد اشتراك العميل يدوياً (من الإدارة)
     */
    public function subscriptionsRenew(Request $request, int $userId)
    {
        $request->validate([
            'plan_id'           => 'required|exists:subscriptions_plans,id',
            'payment_reference' => 'nullable|string|max:255',
            'paid_amount'       => 'required|numeric|min:0',
            'renewal_type'      => 'required|in:new,renewal,upgrade,extension',
            'coupon_code'       => 'nullable|string|max:100',
        ]);

        $couponCode = trim((string) $request->coupon_code);
        if ($couponCode !== '') {
            $plan = SubscriptionsPlan::findOrFail($request->plan_id);
            $pricing = $this->service->calculatePlanPricing($plan);
            $couponValidation = $this->service->validateCouponForPlan(
                couponCode: $couponCode,
                planId: (int) $request->plan_id,
                userId: $userId,
                totalBeforeCoupon: (float) ($pricing['total_price'] ?? 0)
            );

            if (empty($couponValidation['valid'])) {
                return back()->withInput()->with('error', (string) ($couponValidation['message'] ?? 'الكوبون غير صالح.'));
            }
        }

        $newSub = $this->service->renewSubscription(
            userId:           $userId,
            planId:           $request->plan_id,
            renewalType:      $request->renewal_type,
            paymentReference: $request->payment_reference ?? '',
            paidAmount:       $request->paid_amount,
            adminId:          auth()->id(),
            couponCode:       $couponCode !== '' ? $couponCode : null
        );

        return redirect()->route('admin.subscriptions.user-subscriptions.show', $newSub)
            ->with('success', 'تم تجديد الاشتراك بنجاح.');
    }

    /**
     * تمديد صلاحية الاشتراك
     */
    public function subscriptionsExtend(Request $request, UserSubscription $userSubscription)
    {
        $request->validate([
            'extra_days'        => 'required|integer|min:1',
            'extension_fee'     => 'required|numeric|min:0',
            'payment_reference' => 'nullable|string|max:255',
        ]);

        $this->service->extendSubscription(
            subscription:     $userSubscription,
            extraDays:        $request->extra_days,
            extensionFee:     $request->extension_fee,
            paymentReference: $request->payment_reference ?? '',
            adminId:          auth()->id()
        );

        return back()->with('success', 'تم تمديد الاشتراك بنجاح.');
    }

    /**
     * إلغاء اشتراك
     */
    public function subscriptionsCancel(Request $request, UserSubscription $userSubscription)
    {
        $userSubscription->update([
            'subscription_status' => 'cancelled',
            'notes' => $request->reason ?? 'تم الإلغاء من الإدارة',
        ]);

        return back()->with('success', 'تم إلغاء الاشتراك.');
    }

    // =========================================================
    // إعدادات الاشتراكات
    // =========================================================

    public function settingsIndex()
    {
        $this->ensureDefaultSettingOptions();

        $categories = SubscriptionsCategory::orderBy('id', 'desc')->get();
        $periods = SubscriptionPeriod::orderBy('id', 'desc')->get();

        $businessTypes = SubscriptionSettingOption::where('type', 'business_type')
            ->orderBy('sort_order')->orderBy('id')->get();

        $storeTypes = SubscriptionSettingOption::where('type', 'store_type')
            ->orderBy('sort_order')->orderBy('id')->get();

        $featureKeys = SubscriptionSettingOption::where('type', 'feature_key')
            ->orderBy('sort_order')->orderBy('id')->get();

        $coupons = SubscriptionCoupon::query()
            ->orderByDesc('id')
            ->get();

        $allPlans = SubscriptionsPlan::query()
            ->orderBy('title_ar')
            ->get(['id', 'title_ar']);

        $regions = Region::with('country')->orderBy('title_ar')->get();
        $regionSettings = SubscriptionRegionSetting::get()->keyBy('region_id');
        $subscriptionTaxSetting = TaxSetting::query()
            ->where('title_en', 'subscription_tax')
            ->latest('id')
            ->first();

        return view('admin.subscriptions.settings.index', compact(
            'categories',
            'periods',
            'businessTypes',
            'storeTypes',
            'featureKeys',
            'coupons',
            'allPlans',
            'regions',
            'regionSettings',
            'subscriptionTaxSetting'
        ));
    }

    public function settingsStore(Request $request)
    {
        $section = $request->input('section');
        $action = $request->input('action', 'create');

        if ($section === 'category') {
            if ($action === 'create') {
                $request->validate([
                    'title_ar' => 'required|string|max:255',
                    'title_en' => 'nullable|string|max:255',
                ]);

                SubscriptionsCategory::create([
                    'title_ar' => $request->title_ar,
                    'title_en' => $request->title_en,
                    'description_ar' => $request->description_ar,
                    'description_en' => $request->description_en,
                    'status' => $request->status ?? '0',
                ]);

                return back()->with('success', 'تمت إضافة فئة اشتراك جديدة.');
            }

            $category = SubscriptionsCategory::findOrFail((int) $request->item_id);

            if ($action === 'update') {
                $request->validate([
                    'title_ar' => 'required|string|max:255',
                    'title_en' => 'nullable|string|max:255',
                ]);

                $category->update([
                    'title_ar' => $request->title_ar,
                    'title_en' => $request->title_en,
                ]);

                return back()->with('success', 'تم تعديل الفئة.');
            }

            if ($action === 'toggle') {
                $category->update(['status' => $category->status === '0' ? '1' : '0']);
                return back()->with('success', 'تم تحديث حالة الفئة.');
            }

            if ($action === 'delete') {
                $category->delete();
                return back()->with('success', 'تم حذف الفئة.');
            }
        }

        if ($section === 'period') {
            if ($action === 'create') {
                $request->validate([
                    'title_ar' => 'required|string|max:255',
                    'title_en' => 'nullable|string|max:255',
                    'period'   => 'required|integer|min:1',
                ]);

                SubscriptionPeriod::create([
                    'title_ar' => $request->title_ar,
                    'title_en' => $request->title_en,
                    'period' => $request->period,
                    'status' => $request->status ?? '1',
                ]);

                return back()->with('success', 'تمت إضافة فترة اشتراك جديدة.');
            }

            $period = SubscriptionPeriod::findOrFail((int) $request->item_id);

            if ($action === 'update') {
                $request->validate([
                    'title_ar' => 'required|string|max:255',
                    'title_en' => 'nullable|string|max:255',
                    'period'   => 'required|integer|min:1',
                ]);

                $period->update([
                    'title_ar' => $request->title_ar,
                    'title_en' => $request->title_en,
                    'period' => $request->period,
                ]);

                return back()->with('success', 'تم تعديل فترة الاشتراك.');
            }

            if ($action === 'toggle') {
                $period->update(['status' => $period->status === '1' ? '0' : '1']);
                return back()->with('success', 'تم تحديث حالة فترة الاشتراك.');
            }

            if ($action === 'delete') {
                $period->delete();
                return back()->with('success', 'تم حذف فترة الاشتراك.');
            }
        }

        if (in_array($section, ['business_type', 'store_type', 'feature_key'], true)) {
            if ($action === 'create') {
                $request->validate([
                    'code'      => 'required|string|max:100',
                    'name_ar'   => 'required|string|max:255',
                    'name_en'   => 'nullable|string|max:255',
                    'sort_order'=> 'nullable|integer|min:0',
                ]);

                SubscriptionSettingOption::updateOrCreate(
                    ['type' => $section, 'code' => $request->code],
                    [
                        'name_ar' => $request->name_ar,
                        'name_en' => $request->name_en,
                        'is_active' => $request->boolean('is_active', true),
                        'sort_order' => $request->sort_order ?? 0,
                    ]
                );

                return back()->with('success', 'تم حفظ الإعداد.');
            }

            $option = SubscriptionSettingOption::where('type', $section)
                ->findOrFail((int) $request->item_id);

            if ($action === 'update') {
                $request->validate([
                    'code'      => 'required|string|max:100',
                    'name_ar'   => 'required|string|max:255',
                    'name_en'   => 'nullable|string|max:255',
                    'sort_order'=> 'nullable|integer|min:0',
                ]);

                $option->update([
                    'code' => $request->code,
                    'name_ar' => $request->name_ar,
                    'name_en' => $request->name_en,
                    'sort_order' => $request->sort_order ?? 0,
                ]);

                return back()->with('success', 'تم تعديل الإعداد.');
            }

            if ($action === 'toggle') {
                $option->update(['is_active' => !$option->is_active]);
                return back()->with('success', 'تم تحديث حالة العنصر.');
            }

            if ($action === 'delete') {
                $option->delete();
                return back()->with('success', 'تم حذف العنصر.');
            }
        }

        if ($section === 'coupon') {
            if ($action === 'create') {
                $request->validate([
                    'code' => 'required|string|max:100|unique:subscription_coupons,code',
                    'discount_type' => 'required|in:percent,fixed',
                    'discount_value' => 'required|numeric|min:0',
                    'max_discount_amount' => 'nullable|numeric|min:0',
                    'min_order_amount' => 'nullable|numeric|min:0',
                    'usage_limit' => 'nullable|integer|min:1',
                    'per_user_limit' => 'nullable|integer|min:1',
                    'starts_at' => 'nullable|date',
                    'ends_at' => 'nullable|date|after_or_equal:starts_at',
                    'applicable_plan_ids' => 'nullable|array',
                    'applicable_plan_ids.*' => 'integer|exists:subscriptions_plans,id',
                ]);

                SubscriptionCoupon::create([
                    'code' => strtoupper(trim((string) $request->code)),
                    'name_ar' => $request->name_ar,
                    'name_en' => $request->name_en,
                    'discount_type' => $request->discount_type,
                    'discount_value' => $request->discount_value,
                    'max_discount_amount' => $request->max_discount_amount,
                    'min_order_amount' => $request->min_order_amount ?? 0,
                    'usage_limit' => $request->usage_limit,
                    'per_user_limit' => $request->per_user_limit,
                    'starts_at' => $request->starts_at,
                    'ends_at' => $request->ends_at,
                    'is_active' => $request->boolean('is_active', true),
                    'applicable_plan_ids' => $request->input('applicable_plan_ids', []),
                    'notes' => $request->notes,
                ]);

                return back()->with('success', 'تمت إضافة الكوبون.');
            }

            $coupon = SubscriptionCoupon::findOrFail((int) $request->item_id);

            if ($action === 'update') {
                $request->validate([
                    'code' => 'required|string|max:100|unique:subscription_coupons,code,' . $coupon->id,
                    'discount_type' => 'required|in:percent,fixed',
                    'discount_value' => 'required|numeric|min:0',
                    'max_discount_amount' => 'nullable|numeric|min:0',
                    'min_order_amount' => 'nullable|numeric|min:0',
                    'usage_limit' => 'nullable|integer|min:1',
                    'per_user_limit' => 'nullable|integer|min:1',
                    'starts_at' => 'nullable|date',
                    'ends_at' => 'nullable|date|after_or_equal:starts_at',
                    'applicable_plan_ids' => 'nullable|array',
                    'applicable_plan_ids.*' => 'integer|exists:subscriptions_plans,id',
                ]);

                $coupon->update([
                    'code' => strtoupper(trim((string) $request->code)),
                    'name_ar' => $request->name_ar,
                    'name_en' => $request->name_en,
                    'discount_type' => $request->discount_type,
                    'discount_value' => $request->discount_value,
                    'max_discount_amount' => $request->max_discount_amount,
                    'min_order_amount' => $request->min_order_amount ?? 0,
                    'usage_limit' => $request->usage_limit,
                    'per_user_limit' => $request->per_user_limit,
                    'starts_at' => $request->starts_at,
                    'ends_at' => $request->ends_at,
                    'is_active' => $request->boolean('is_active', $coupon->is_active),
                    'applicable_plan_ids' => $request->input('applicable_plan_ids', []),
                    'notes' => $request->notes,
                ]);

                return back()->with('success', 'تم تعديل الكوبون.');
            }

            if ($action === 'toggle') {
                $coupon->update(['is_active' => !$coupon->is_active]);
                return back()->with('success', 'تم تحديث حالة الكوبون.');
            }

            if ($action === 'delete') {
                $coupon->delete();
                return back()->with('success', 'تم حذف الكوبون.');
            }
        }

        if ($section === 'tax') {
            $request->validate([
                'status' => 'required|in:1,2',
                'tax_type' => 'required|in:1,2',
                'tax_value' => 'required|numeric|min:0',
            ]);

            TaxSetting::updateOrCreate(
                ['title_en' => 'subscription_tax'],
                [
                    'title_ar' => 'ضريبة الاشتراكات',
                    'tax_type' => $request->tax_type,
                    'tax_value' => $request->tax_value,
                    'status' => $request->status,
                ]
            );

            return back()->with('success', 'تم حفظ إعدادات ضريبة الاشتراكات.');
        }

        return back()->with('error', 'قسم الإعدادات غير معروف.');
    }

    public function settingsSaveRegions(Request $request)
    {
        $request->validate([
            'regions' => 'nullable|array',
        ]);

        $input = $request->input('regions', []);

        $allRegionIds = Region::pluck('id');

        foreach ($allRegionIds as $regionId) {
            $row = $input[$regionId] ?? [];

            SubscriptionRegionSetting::updateOrCreate(
                ['region_id' => $regionId],
                [
                    'is_enabled' => !empty($row['is_enabled']),
                    'default_price_per_shipment' => (float) ($row['default_price_per_shipment'] ?? 0),
                ]
            );
        }

        return back()->with('success', 'تم حفظ إعدادات المناطق بنجاح.');
    }

    // =========================================================
    // تقارير ولوحة تحكم الاشتراكات
    // =========================================================

    public function dashboard()
    {
        $stats = [
            'total_active'      => UserSubscription::where('subscription_status', 'active')->count(),
            'total_expired'     => UserSubscription::where('subscription_status', 'expired')->count(),
            'total_exhausted'   => UserSubscription::where('subscription_status', 'exhausted')->count(),
            'revenue_month'     => UserSubscription::whereMonth('created_at', now()->month)
                                    ->whereYear('created_at', now()->year)
                                    ->sum('paid_amount'),
            'expiring_soon'     => UserSubscription::where('subscription_status', 'active')
                                    ->where('expiry_date', '<=', now()->addDays(7)->toDateString())
                                    ->count(),
            'low_shipments'     => UserSubscription::where('subscription_status', 'active')
                                    ->where('remaining_shipments', '<=', 5)
                                    ->count(),
        ];

        $recentSubs = UserSubscription::with(['user', 'subscription'])
            ->latest()->limit(10)->get();

        $expiringSoon = UserSubscription::with(['user', 'subscription'])
            ->where('subscription_status', 'active')
            ->where('expiry_date', '<=', now()->addDays(7)->toDateString())
            ->get();

        return view('admin.subscriptions.dashboard', compact('stats', 'recentSubs', 'expiringSoon'));
    }

    // =========================================================
    // إدارة الرسوم الإضافية
    // =========================================================

    public function extraChargesIndex(Request $request)
    {
        $charges = SubscriptionExtraCharge::with(['user', 'order'])
            ->when($request->user_id, fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->status,  fn ($q) => $q->where('status', $request->status))
            ->latest()->paginate(25);

        return view('admin.subscriptions.extra-charges.index', compact('charges'));
    }

    public function extraChargesStore(Request $request)
    {
        $request->validate([
            'user_id'        => 'required|exists:users,id',
            'charge_type'    => 'required|string',
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:prepaid,deduct_from_cod,invoice',
            'order_id'       => 'nullable|exists:orders,id',
            'description_ar' => 'nullable|string|max:500',
        ]);

        $sub = $this->service->getActiveSubscription($request->user_id);

        $this->service->addExtraCharge(
            userId:              $request->user_id,
            chargeType:          $request->charge_type,
            amount:              $request->amount,
            paymentMethod:       $request->payment_method,
            orderId:             $request->order_id,
            userSubscriptionId:  $sub?->id,
            description:         $request->description_ar ?? ''
        );

        return back()->with('success', 'تم إضافة الرسوم الإضافية.');
    }

    /**
     * تسوية رسوم إضافية يدوياً (تغيير الحالة إلى paid)
     */
    public function extraChargesSettle(SubscriptionExtraCharge $charge)
    {
        $charge->update(['status' => 'paid']);
        app(CentralFinanceBillingService::class)->settleExtraChargeInvoice($charge);
        return back()->with('success', 'تم تسوية الرسوم.');
    }

    // =========================================================
    // إضافة اشتراك يدوي من الإدارة
    // =========================================================

    public function subscriptionsCreateManual(Request $request)
    {
        if ($request->isMethod('get')) {
            $plans = SubscriptionsPlan::where('status', '0')->get();
            $users = User::where('user_type', 'customer')->orWhereNull('user_type')
                ->orderBy('name')->get(['id','name','email','mobile']);
            return view('admin.subscriptions.user-subscriptions.create-manual', compact('plans', 'users'));
        }

        $request->validate([
            'user_id'           => 'required|exists:users,id',
            'plan_id'           => 'required|exists:subscriptions_plans,id',
            'paid_amount'       => 'required|numeric|min:0',
            'payment_reference' => 'nullable|string|max:255',
            'coupon_code'       => 'nullable|string|max:100',
        ]);

        $couponCode = trim((string) $request->coupon_code);
        if ($couponCode !== '') {
            $plan = SubscriptionsPlan::findOrFail($request->plan_id);
            $pricing = $this->service->calculatePlanPricing($plan);
            $couponValidation = $this->service->validateCouponForPlan(
                couponCode: $couponCode,
                planId: (int) $request->plan_id,
                userId: (int) $request->user_id,
                totalBeforeCoupon: (float) ($pricing['total_price'] ?? 0)
            );

            if (empty($couponValidation['valid'])) {
                return back()->withInput()->with('error', (string) ($couponValidation['message'] ?? 'الكوبون غير صالح.'));
            }
        }

        $existing = $this->service->getActiveSubscription($request->user_id);

        if ($existing) {
            $newSub = $this->service->renewSubscription(
                userId:           $request->user_id,
                planId:           $request->plan_id,
                renewalType:      'new',
                paymentReference: $request->payment_reference ?? '',
                paidAmount:       $request->paid_amount,
                adminId:          auth()->id(),
                couponCode:       $couponCode !== '' ? $couponCode : null
            );
        } else {
            $newSub = $this->service->createSubscription(
                userId:           $request->user_id,
                planId:           $request->plan_id,
                paymentReference: $request->payment_reference ?? '',
                paidAmount:       $request->paid_amount,
                couponCode:       $couponCode !== '' ? $couponCode : null
            );
        }

        return redirect()->route('admin.subscriptions.user-subscriptions.show', $newSub)
            ->with('success', 'تم إنشاء الاشتراك بنجاح.');
    }
}
