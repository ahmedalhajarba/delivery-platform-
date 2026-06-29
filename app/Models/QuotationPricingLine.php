<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class QuotationPricingLine extends Model
{
    public $table = 'quotation_pricing_lines';

    const SERVICE_TYPES = [
        // Platform services (current)
        'single_order_domestic' => 'طلب فردي - شحن محلي',
        'single_order_international' => 'طلب فردي - شحن دولي',
        'extra_services' => 'خدمات إضافية',

        // Legacy keys (kept for old quotations)
        'standard' => 'شحن عادي',
        'international' => 'شحن دولي',
        'express' => 'خدمة إضافية/سريعة',
    ];

    protected $fillable = [
        'quotation_id',
        'service_type',
        'zone_from',
        'zone_to',
        'weight_unit',
        'base_price',
        'price_per_kg',
        'free_weight_kg',
        'include_insurance',
        'insurance_rate',
        'include_packaging',
        'packaging_price',
        'include_vat',
        'vat_rate',
        'notes',
    ];

    protected $casts = [
        'include_insurance' => 'boolean',
        'include_packaging' => 'boolean',
        'include_vat' => 'boolean',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function getServiceLabelAttribute()
    {
        $type = (string) $this->service_type;

        if (isset(self::SERVICE_TYPES[$type])) {
            return self::SERVICE_TYPES[$type];
        }

        if (Str::startsWith($type, 'subscription_plan_')) {
            $planId = (int) Str::after($type, 'subscription_plan_');
            $plan = SubscriptionsPlan::query()->find($planId);

            if ($plan) {
                return 'اشتراك - ' . ($plan->title_ar ?: $plan->title_en ?: ('#' . $planId));
            }

            return 'اشتراك #' . $planId;
        }

        return $type;
    }
}
