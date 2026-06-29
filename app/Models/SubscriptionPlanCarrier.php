<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlanCarrier extends Model
{
    use HasFactory;

    public $table = 'subscription_plan_carriers';

    protected $fillable = [
        'plan_id',
        'carrier_company_id',
        'allocated_shipments',
        'price_per_shipment',
        'is_active',
    ];

    protected $casts = [
        'allocated_shipments' => 'integer',
        'price_per_shipment' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function plan()
    {
        return $this->belongsTo(SubscriptionsPlan::class, 'plan_id');
    }

    public function carrierCompany()
    {
        return $this->belongsTo(CarrierCompany::class, 'carrier_company_id');
    }
}
