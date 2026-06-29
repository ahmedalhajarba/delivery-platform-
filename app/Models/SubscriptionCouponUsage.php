<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionCouponUsage extends Model
{
    use HasFactory;

    public $table = 'subscription_coupon_usages';

    protected $fillable = [
        'coupon_id',
        'user_id',
        'plan_id',
        'user_subscription_id',
        'discount_amount',
        'total_before_discount',
        'total_after_discount',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function coupon()
    {
        return $this->belongsTo(SubscriptionCoupon::class, 'coupon_id');
    }
}
