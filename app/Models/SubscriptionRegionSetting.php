<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionRegionSetting extends Model
{
    public $table = 'subscription_region_settings';

    protected $fillable = [
        'region_id',
        'is_enabled',
        'default_price_per_shipment',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }
}
