<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtraServiceSetting extends Model
{
    public $table = 'extra_service_settings';

    protected $fillable = [
        'overweight_rate',
        'packaging_enabled',
        'packaging_cost',
        'storage_enabled',
        'storage_normal_daily',
        'storage_cold_daily',
        'storage_free_days',
        'return_enabled',
        'return_cost',
        'delivery_attempt_enabled',
        'delivery_free_attempts',
        'delivery_attempt_cost',
        // التأمين
        'insurance_enabled',
        'insurance_rate',
        // الضريبة
        'vat_enabled',
        'vat_rate',
        'vat_on_shipping',
        'vat_on_insurance',
        'vat_on_extras',
        // رسوم الشحن الخاصة
        'cold_shipping_surcharge',
        'frozen_shipping_surcharge',
        'dry_shipping_surcharge',
        // تخزين مجمد
        'storage_frozen_daily',
    ];

    protected $casts = [
        'packaging_enabled'        => 'boolean',
        'storage_enabled'          => 'boolean',
        'return_enabled'           => 'boolean',
        'delivery_attempt_enabled' => 'boolean',
        'insurance_enabled'        => 'boolean',
        'vat_enabled'              => 'boolean',
        'vat_on_shipping'          => 'boolean',
        'vat_on_insurance'         => 'boolean',
        'vat_on_extras'            => 'boolean',
        'overweight_rate'          => 'decimal:2',
        'packaging_cost'           => 'decimal:2',
        'storage_normal_daily'     => 'decimal:2',
        'storage_cold_daily'       => 'decimal:2',
        'return_cost'              => 'decimal:2',
        'delivery_attempt_cost'    => 'decimal:2',
        'insurance_rate'           => 'decimal:2',
        'vat_rate'                 => 'decimal:2',
        'cold_shipping_surcharge'  => 'decimal:2',
        'frozen_shipping_surcharge'=> 'decimal:2',
        'dry_shipping_surcharge'   => 'decimal:2',
        'storage_frozen_daily'     => 'decimal:2',
    ];
}
