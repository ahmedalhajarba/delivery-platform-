<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionCoupon extends Model
{
    use HasFactory;
    use SoftDeletes;

    public $table = 'subscription_coupons';

    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'min_order_amount',
        'usage_limit',
        'used_count',
        'per_user_limit',
        'starts_at',
        'ends_at',
        'is_active',
        'applicable_plan_ids',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'applicable_plan_ids' => 'array',
    ];

    public function usages()
    {
        return $this->hasMany(SubscriptionCouponUsage::class, 'coupon_id');
    }
}
