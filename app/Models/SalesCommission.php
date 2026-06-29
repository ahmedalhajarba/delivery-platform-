<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesCommission extends Model
{
    public $table = 'sales_commissions';

    const SETTLEMENT_STATUSES = [
        'pending' => 'معلق',
        'approved' => 'معتمد',
        'paid' => 'مدفوع',
        'disputed' => 'متنازع عليه',
    ];

    protected $fillable = [
        'user_id',
        'role_type',
        'source_type',
        'source_id',
        'period_year',
        'period_month',
        'base_amount',
        'commission_amount',
        'bonus_amount',
        'net_amount',
        'target_amount',
        'achieved_amount',
        'target_achievement_percent',
        'incentive_adjustment',
        'discount_impact_amount',
        'settlement_amount',
        'status',
        'settlement_status',
        'approved_by',
        'calculated_at',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'target_amount' => 'decimal:2',
        'achieved_amount' => 'decimal:2',
        'target_achievement_percent' => 'decimal:2',
        'incentive_adjustment' => 'decimal:2',
        'discount_impact_amount' => 'decimal:2',
        'settlement_amount' => 'decimal:2',
        'calculated_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
