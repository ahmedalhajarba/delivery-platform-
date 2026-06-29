<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerOrderSetting extends Model
{
    public $table = 'customer_order_settings';

    protected $fillable = [
        'user_id', 'contract_id',
        'allow_standard', 'allow_cold', 'allow_dry', 'allow_frozen',
        'allow_express', 'allow_international', 'allow_cod', 'allow_storage',
        'max_orders_per_day', 'max_weight_per_order', 'max_packages_per_order', 'max_cod_amount',
        'billing_cycle', 'deferred_payment_enabled', 'deferred_days', 'credit_limit',
        'discount_percent', 'use_contract_pricing',
    ];

    protected $casts = [
        'allow_standard'           => 'boolean',
        'allow_cold'               => 'boolean',
        'allow_dry'                => 'boolean',
        'allow_frozen'             => 'boolean',
        'allow_express'            => 'boolean',
        'allow_international'      => 'boolean',
        'allow_cod'                => 'boolean',
        'allow_storage'            => 'boolean',
        'deferred_payment_enabled' => 'boolean',
        'use_contract_pricing'     => 'boolean',
    ];

    public function user()     { return $this->belongsTo(User::class); }
    public function contract() { return $this->belongsTo(Contract::class); }

    public static function getOrCreateForUser(int $userId): self
    {
        return self::firstOrCreate(['user_id' => $userId], ['user_id' => $userId]);
    }
}
