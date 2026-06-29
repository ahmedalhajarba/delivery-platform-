<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionExtraCharge extends Model
{
    public $table = 'subscription_extra_charges';

    public const CHARGE_TYPES = [
        'packaging'       => 'تغليف',
        'storage'         => 'تخزين',
        'carton'          => 'كراتين',
        'courier_booking' => 'حجز مندوب',
        'insurance'       => 'تأمين',
        'other'           => 'أخرى',
    ];

    protected $fillable = [
        'user_id',
        'order_id',
        'user_subscription_id',
        'charge_type',
        'description_ar',
        'amount',
        'payment_method',
        'status',
        'payment_reference',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function userSubscription()
    {
        return $this->belongsTo(UserSubscription::class);
    }
}
