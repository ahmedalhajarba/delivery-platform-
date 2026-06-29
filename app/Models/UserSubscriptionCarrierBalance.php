<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSubscriptionCarrierBalance extends Model
{
    use HasFactory;

    public $table = 'user_subscription_carrier_balances';

    protected $fillable = [
        'user_subscription_id',
        'plan_carrier_id',
        'carrier_company_id',
        'allocated_shipments',
        'used_shipments',
        'remaining_shipments',
        'price_per_shipment',
        'is_active',
    ];

    protected $casts = [
        'allocated_shipments' => 'integer',
        'used_shipments' => 'integer',
        'remaining_shipments' => 'integer',
        'price_per_shipment' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function userSubscription()
    {
        return $this->belongsTo(UserSubscription::class, 'user_subscription_id');
    }

    public function planCarrier()
    {
        return $this->belongsTo(SubscriptionPlanCarrier::class, 'plan_carrier_id');
    }

    public function carrierCompany()
    {
        return $this->belongsTo(CarrierCompany::class, 'carrier_company_id');
    }

    public function deductOne(): bool
    {
        if ((int) $this->remaining_shipments <= 0) {
            return false;
        }

        $this->increment('used_shipments');
        $this->decrement('remaining_shipments');

        return true;
    }
}
