<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlanRegion extends Model
{
    public $table = 'subscription_plan_regions';

    protected $fillable = [
        'plan_id',
        'region_id',
        'price_per_shipment',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function plan()
    {
        return $this->belongsTo(SubscriptionsPlan::class, 'plan_id');
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }
}
