<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlanFeature extends Model
{
    public $table = 'subscription_plan_features';

    public const FEATURE_KEYS = [
        'packaging'       => 'التغليف',
        'storage'         => 'التخزين',
        'carton'          => 'الكراتين',
        'courier_booking' => 'حجز مندوب',
        'insurance'       => 'التأمين',
        'express'         => 'الشحن السريع',
        'cod'             => 'الدفع عند الاستلام',
        'bank_transfer'   => 'التحويل البنكي',
        'priority_pickup' => 'الاستلام الأولوي',
        'api_access'      => 'الوصول لـ API',
        'dashboard'       => 'لوحة التحكم',
        'reports'         => 'التقارير',
        'other'           => 'أخرى',
    ];

    protected $fillable = [
        'plan_id',
        'feature_key',
        'feature_name_ar',
        'feature_name_en',
        'feature_type',
        'is_included',
        'extra_cost',
        'unit',
        'sort_order',
    ];

    protected $casts = [
        'is_included' => 'boolean',
    ];

    public function plan()
    {
        return $this->belongsTo(SubscriptionsPlan::class, 'plan_id');
    }
}
