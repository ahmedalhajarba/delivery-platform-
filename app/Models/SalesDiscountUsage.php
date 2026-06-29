<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesDiscountUsage extends Model
{
    public $table = 'sales_discount_usages';

    protected $fillable = [
        'sales_discount_code_id',
        'user_id',
        'invoice_id',
        'applied_by',
        'actor_role',
        'subtotal',
        'discount_amount',
        'final_total',
        'applied_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'final_total' => 'decimal:2',
        'applied_at' => 'datetime',
    ];
}
