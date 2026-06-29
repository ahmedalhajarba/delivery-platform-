<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliverySpeedSetting extends Model
{
    public $table = 'delivery_speed_settings';

    protected $fillable = [
        'code',
        'label_ar',
        'label_en',
        'description_ar',
        'icon',
        'color',
        'max_hours',
        'surcharge',
        'surcharge_percent',
        'is_flat_surcharge',
        'enabled',
        'sort_order',
    ];

    protected $casts = [
        'surcharge'         => 'decimal:2',
        'surcharge_percent' => 'decimal:2',
        'is_flat_surcharge' => 'boolean',
        'enabled'           => 'boolean',
        'max_hours'         => 'integer',
        'sort_order'        => 'integer',
    ];

    /** All enabled speeds ordered */
    public static function enabled()
    {
        return static::where('enabled', true)->orderBy('sort_order')->get();
    }

    /** Calculate surcharge amount given base shipping cost */
    public function calcSurcharge(float $baseShipping): float
    {
        if (!$this->is_flat_surcharge) {
            return round($baseShipping * ($this->surcharge_percent / 100), 2);
        }
        return (float) $this->surcharge;
    }
}
