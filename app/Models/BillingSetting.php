<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingSetting extends Model
{
    public $table = 'billing_settings';

    protected $fillable = [
        'bank_name',
        'bank_account_name',
        'iban',
        'account_number',
        'swift_code',
        'bank_branch',
        'default_currency',
        'vat_number',
        'finance_early_discount_percent',
        'sales_default_commission_percent',
        'settlement_mode',
        'auto_settlement_generation',
        'referral_new_customer_commission_enabled',
        'referral_new_customer_commission_amount',
        'coupon_sales_commission_enabled',
        'coupon_sales_commission_percent',
        'payment_instructions',
    ];

    protected $casts = [
        'finance_early_discount_percent' => 'decimal:2',
        'sales_default_commission_percent' => 'decimal:2',
        'referral_new_customer_commission_enabled' => 'boolean',
        'referral_new_customer_commission_amount' => 'decimal:2',
        'coupon_sales_commission_enabled' => 'boolean',
        'coupon_sales_commission_percent' => 'decimal:2',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'default_currency' => 'SAR',
            'finance_early_discount_percent' => 0,
            'sales_default_commission_percent' => 0,
            'settlement_mode' => 'manual',
            'auto_settlement_generation' => 'none',
            'referral_new_customer_commission_enabled' => false,
            'referral_new_customer_commission_amount' => 0,
            'coupon_sales_commission_enabled' => false,
            'coupon_sales_commission_percent' => 0,
        ]);
    }
}
