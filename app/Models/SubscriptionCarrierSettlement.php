<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionCarrierSettlement extends Model
{
    use HasFactory;

    public $table = 'subscription_carrier_settlements';

    protected $fillable = [
        'user_subscription_id',
        'order_id',
        'requested_carrier_company_id',
        'consumed_carrier_company_id',
        'requested_price_per_shipment',
        'consumed_price_per_shipment',
        'price_difference',
        'settlement_status',
        'notes',
    ];

    protected $casts = [
        'requested_price_per_shipment' => 'decimal:2',
        'consumed_price_per_shipment' => 'decimal:2',
        'price_difference' => 'decimal:2',
    ];

    public function userSubscription()
    {
        return $this->belongsTo(UserSubscription::class, 'user_subscription_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function requestedCarrier()
    {
        return $this->belongsTo(CarrierCompany::class, 'requested_carrier_company_id');
    }

    public function consumedCarrier()
    {
        return $this->belongsTo(CarrierCompany::class, 'consumed_carrier_company_id');
    }
}
