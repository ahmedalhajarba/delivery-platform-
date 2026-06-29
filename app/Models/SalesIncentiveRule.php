<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesIncentiveRule extends Model
{
    use SoftDeletes;

    public $table = 'sales_incentive_rules';

    protected $fillable = [
        'name',
        'role_type',
        'basis',
        'min_amount',
        'commission_type',
        'commission_value',
        'bonus_threshold_amount',
        'bonus_amount',
        'is_active',
    ];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'commission_value' => 'decimal:2',
        'bonus_threshold_amount' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
