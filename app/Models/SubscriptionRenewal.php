<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionRenewal extends Model
{
    public $table = 'subscription_renewals';

    protected $fillable = [
        'user_subscription_id',
        'user_id',
        'plan_id',
        'renewal_type',
        'shipments_added',
        'shipments_before',
        'previous_expiry',
        'new_expiry',
        'amount_paid',
        'forfeited_from_previous',
        'payment_reference',
        'notes',
        'processed_by',
    ];

    public function userSubscription()
    {
        return $this->belongsTo(UserSubscription::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionsPlan::class, 'plan_id');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
