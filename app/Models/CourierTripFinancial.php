<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourierTripFinancial extends Model
{
    use HasFactory;

    const STATUS = [
        'draft' => 'مسودة',
        'approved' => 'معتمدة',
        'paid' => 'مدفوعة',
        'cancelled' => 'ملغاة',
    ];

    protected $fillable = [
        'branch_employee_id',
        'trip_code',
        'trip_date',
        'base_wage',
        'commission_amount',
        'bonus_amount',
        'deduction_amount',
        'operational_cost',
        'net_amount',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'trip_date' => 'date',
        'base_wage' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'deduction_amount' => 'decimal:2',
        'operational_cost' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function (CourierTripFinancial $trip) {
            $trip->net_amount = round(
                (float) $trip->base_wage
                + (float) $trip->commission_amount
                + (float) $trip->bonus_amount
                - (float) $trip->deduction_amount
                - (float) $trip->operational_cost,
                2
            );
        });
    }

    public function employee()
    {
        return $this->belongsTo(BranchEmployee::class, 'branch_employee_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function settlementItems()
    {
        return $this->hasMany(CourierSettlementItem::class, 'courier_trip_financial_id');
    }
}
