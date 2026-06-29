<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\DeliverySpeedSetting;
use App\Models\ExtraServiceSetting;
use App\Models\IntlShippingRate;
use App\Models\OrderSetting;
use App\Models\ServicePurchase;
use App\Models\ShippingPrice;
use App\Models\SubscriptionsPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class PlatformServicesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $plans = SubscriptionsPlan::query()
            ->where('status', '0')
            ->orderBy('m_price')
            ->get();

        $countries = Country::query()->orderBy('name')->get();
        $cities = City::query()->orderBy('title_ar')->get();
        $deliverySpeeds = DeliverySpeedSetting::enabled();
        $extraSettings = ExtraServiceSetting::query()->first();
        $orderSetting = OrderSetting::query()->first();

        return view('user.platform-services.index', compact(
            'plans',
            'countries',
            'cities',
            'deliverySpeeds',
            'extraSettings',
            'orderSetting'
        ));
    }

    public function show(string $type, ?int $id = null)
    {
        if ($type === 'subscription' && $id) {
            $plan = SubscriptionsPlan::query()->findOrFail($id);
            return view('user.platform-services.show', compact('type', 'plan'));
        }

        return view('user.platform-services.show', compact('type', 'id'));
    }

    public function summary(Request $request)
    {
        $payload = $this->validateAndBuildPayload($request);

        return view('user.platform-services.summary', [
            'payload' => $payload,
            'input' => $request->all(),
        ]);
    }

    public function payment(Request $request)
    {
        return back()->with('error', 'الدفع بالبطاقات الائتمانية مجمد حاليا وسيتم تفعيله قريبا.');
    }

    public function bankTransfer(Request $request)
    {
        $request->validate([
            'bank_name' => ['required', 'string', 'max:255'],
            'bank_iban' => ['required', 'string', 'max:64'],
            'bank_account_number' => ['required', 'string', 'max:64'],
            'transfer_reference' => ['required', 'string', 'max:100'],
            'transfer_receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'user_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $payload = $this->validateAndBuildPayload($request);

        $filePath = $request->file('transfer_receipt')->store('service-purchases/transfers', 'public');

        $purchase = ServicePurchase::query()->create([
            'user_id' => auth()->id(),
            'service_type' => $payload['service_type'],
            'subscription_plan_id' => $payload['subscription_plan_id'],
            'service_name_ar' => $payload['service_name_ar'],
            'service_name_en' => $payload['service_name_en'],
            'service_description' => $payload['service_description'],
            'service_ref' => $payload['service_ref'],
            'unit_price' => $payload['unit_price'],
            'extra_services_amount' => $payload['extra_services_amount'],
            'subtotal' => $payload['subtotal'],
            'tax_enabled' => $payload['tax_enabled'],
            'tax_rate' => $payload['tax_rate'],
            'tax_amount' => $payload['tax_amount'],
            'total_amount' => $payload['total_amount'],
            'currency' => 'SAR',
            'selected_extras' => $payload['selected_extras'],
            'status' => 'reviewing',
            'payment_method' => 'bank_transfer',
            'bank_name' => (string) $request->input('bank_name'),
            'bank_iban' => (string) $request->input('bank_iban'),
            'bank_account_number' => (string) $request->input('bank_account_number'),
            'transfer_reference' => (string) $request->input('transfer_reference'),
            'transfer_receipt_path' => $filePath,
            'paid_at' => now(),
            'user_notes' => $request->input('user_notes'),
        ]);

        return redirect()->route('user.platform-services.receipt', $purchase)
            ->with('message', 'تم استلام طلب الشراء بنجاح وهو الآن قيد المراجعة من الإدارة المالية.');
    }

    public function receipt(ServicePurchase $purchase)
    {
        abort_if($purchase->user_id !== auth()->id(), 403);

        return view('user.platform-services.receipt', compact('purchase'));
    }

    public function invoice(ServicePurchase $purchase)
    {
        abort_if($purchase->user_id !== auth()->id(), 403);

        return view('user.platform-services.invoice', compact('purchase'));
    }

    public function myPurchases()
    {
        $purchases = ServicePurchase::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(15);

        return view('user.platform-services.my-purchases', compact('purchases'));
    }

    private function validateAndBuildPayload(Request $request): array
    {
        $validated = $request->validate([
            'service_type' => ['required', 'in:subscription,single_order'],
            'subscription_plan_id' => ['required_if:service_type,subscription', 'nullable', 'integer'],
            'delivery_scope' => ['required_if:service_type,single_order', 'nullable', 'in:domestic,international'],
            'city_id' => ['required_if:delivery_scope,domestic', 'nullable', 'integer'],
            'destination_country_id' => ['required_if:delivery_scope,international', 'nullable', 'integer'],
            'chargeable_weight' => ['required_if:delivery_scope,international', 'nullable', 'numeric', 'min:0.1'],
            'shipping_type' => ['required_if:delivery_scope,international', 'nullable', 'in:standard,cold,frozen,dry'],
            'delivery_speed' => ['nullable', 'string', 'max:50'],
            'declared_value' => ['nullable', 'numeric', 'min:0'],
            'delivery_attempts' => ['nullable', 'integer', 'min:1'],
            'selected_extras' => ['nullable', 'array'],
            'selected_extras.*' => ['string', 'in:packaging,insurance,return_service,delivery_attempt'],
            'manual_description' => ['nullable', 'string', 'max:1000'],
        ]);

        $extraSettings = ExtraServiceSetting::query()->first();
        $selectedExtras = Arr::wrap($validated['selected_extras'] ?? []);

        $unitPrice = 0.0;
        $serviceNameAr = '';
        $serviceNameEn = '';
        $serviceRef = null;
        $subscriptionPlanId = null;

        if ($validated['service_type'] === 'subscription') {
            $planId = (int) ($validated['subscription_plan_id'] ?? 0);
            $plan = SubscriptionsPlan::query()->findOrFail($planId);
            $unitPrice = (float) ($plan->m_price ?? 0);
            $serviceNameAr = (string) ($plan->title_ar ?: 'اشتراك منصة');
            $serviceNameEn = (string) ($plan->title_en ?: 'Platform Subscription');
            $serviceRef = 'PLAN-' . $plan->id;
            $subscriptionPlanId = $plan->id;
        } else {
            $deliveryScope = $validated['delivery_scope'] ?? 'domestic';
            $shippingType = $validated['shipping_type'] ?? 'standard';

            if ($deliveryScope === 'international') {
                $destinationCountryId = (int) ($validated['destination_country_id'] ?? 0);
                $weight = (float) ($validated['chargeable_weight'] ?? 0);
                $calc = IntlShippingRate::calcPrice($destinationCountryId, $weight, $shippingType);
                if ($calc === null) {
                    throw ValidationException::withMessages([
                        'destination_country_id' => 'لا توجد تسعيرة دولية مطابقة للوزن/الوجهة المحددة.',
                    ]);
                }
                $unitPrice = $calc;
                $serviceNameAr = 'طلب فردي - شحن دولي';
                $serviceNameEn = 'Single Order - International';
                $serviceRef = 'INTL-' . $destinationCountryId;
            } else {
                $city = City::query()->with('governorate.region.country')->findOrFail((int) ($validated['city_id'] ?? 0));
                $governorate = $city->governorate;
                $region = $governorate?->region;
                $country = $region?->country;

                $lookup = ShippingPrice::lookupPrice(
                    $country?->id,
                    $region?->id,
                    $governorate?->id,
                    $city->id
                );

                $unitPrice = (float) ($lookup ?? 0);
                if ($unitPrice <= 0) {
                    $orderSetting = OrderSetting::query()->first();
                    $unitPrice = (float) ($orderSetting->shipping_rate ?? 0);
                }

                $serviceNameAr = 'طلب فردي - شحن محلي';
                $serviceNameEn = 'Single Order - Domestic';
                $serviceRef = 'DOM-' . $city->id;
            }

            $speedCode = $validated['delivery_speed'] ?? null;
            if ($speedCode) {
                $speed = DeliverySpeedSetting::query()->where('code', $speedCode)->first();
                if ($speed) {
                    $unitPrice += (float) $speed->calcSurcharge($unitPrice);
                }
            }
        }

        $extras = [];
        $extrasAmount = 0.0;

        if ($extraSettings) {
            if (in_array('packaging', $selectedExtras, true) && (bool) $extraSettings->packaging_enabled) {
                $amt = (float) ($extraSettings->packaging_cost ?? 0);
                $extras[] = ['code' => 'packaging', 'label' => 'تغليف', 'amount' => round($amt, 2)];
                $extrasAmount += $amt;
            }

            if (in_array('insurance', $selectedExtras, true) && (bool) $extraSettings->insurance_enabled) {
                $declared = (float) ($validated['declared_value'] ?? 0);
                $rate = (float) ($extraSettings->insurance_rate ?? 0);
                $amt = round(($declared * $rate) / 100, 2);
                $extras[] = ['code' => 'insurance', 'label' => 'تأمين الشحنة', 'amount' => $amt];
                $extrasAmount += $amt;
            }

            if (in_array('return_service', $selectedExtras, true) && (bool) $extraSettings->return_enabled) {
                $amt = (float) ($extraSettings->return_cost ?? 0);
                $extras[] = ['code' => 'return_service', 'label' => 'خدمة الإرجاع', 'amount' => round($amt, 2)];
                $extrasAmount += $amt;
            }

            if (in_array('delivery_attempt', $selectedExtras, true) && (bool) $extraSettings->delivery_attempt_enabled) {
                $attempts = (int) ($validated['delivery_attempts'] ?? 1);
                $paidAttempts = max(0, $attempts - (int) ($extraSettings->delivery_free_attempts ?? 0));
                $amt = round($paidAttempts * (float) ($extraSettings->delivery_attempt_cost ?? 0), 2);
                $extras[] = ['code' => 'delivery_attempt', 'label' => 'محاولات تسليم إضافية', 'amount' => $amt];
                $extrasAmount += $amt;
            }
        }

        $subtotal = round($unitPrice + $extrasAmount, 2);

        $taxEnabled = (bool) ($extraSettings->vat_enabled ?? false);
        $taxRate = (float) ($extraSettings->vat_rate ?? 0);
        $taxBase = 0.0;

        if ($taxEnabled) {
            if ((bool) ($extraSettings->vat_on_shipping ?? true)) {
                $taxBase += $unitPrice;
            }
            if ((bool) ($extraSettings->vat_on_extras ?? true)) {
                $taxBase += $extrasAmount;
            }
        }

        $taxAmount = $taxEnabled ? round(($taxBase * $taxRate) / 100, 2) : 0.0;
        $totalAmount = round($subtotal + $taxAmount, 2);

        return [
            'service_type' => $validated['service_type'],
            'subscription_plan_id' => $subscriptionPlanId,
            'service_name_ar' => $serviceNameAr,
            'service_name_en' => $serviceNameEn,
            'service_ref' => $serviceRef,
            'service_description' => $validated['manual_description'] ?? null,
            'unit_price' => round($unitPrice, 2),
            'selected_extras' => $extras,
            'extra_services_amount' => round($extrasAmount, 2),
            'subtotal' => $subtotal,
            'tax_enabled' => $taxEnabled,
            'tax_rate' => round($taxRate, 2),
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ];
    }
}
