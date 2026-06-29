<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourierSettlement extends Model
{
    use HasFactory;

    const STATUS = [
        'draft' => 'مسودة',
        'approved' => 'معتمدة',
        'partial' => 'مدفوعة جزئيا',
        'paid' => 'مدفوعة',
        'cancelled' => 'ملغاة',
    ];

    protected $fillable = [
        'settlement_number',
        'branch_employee_id',
        'settlement_date',
        'period_from',
        'period_to',
        'total_base_amount',
        'total_commission_amount',
        'total_bonus_amount',
        'total_deduction_amount',
        'total_operational_cost',
        'net_amount',
        'paid_amount',
        'balance_amount',
        'status',
        'approved_by',
        'paid_by',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'settlement_date' => 'date',
        'period_from' => 'date',
        'period_to' => 'date',
        'paid_at' => 'datetime',
        'total_base_amount' => 'decimal:2',
        'total_commission_amount' => 'decimal:2',
        'total_bonus_amount' => 'decimal:2',
        'total_deduction_amount' => 'decimal:2',
        'total_operational_cost' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (CourierSettlement $settlement) {
            if (empty($settlement->settlement_number)) {
                $prefix = 'CSET-' . now()->format('Ym') . '-';
                $last = self::query()
                    ->where('settlement_number', 'like', $prefix . '%')
                    ->latest('id')
                    ->first();
                $seq = $last ? ((int) substr($last->settlement_number, strlen($prefix)) + 1) : 1;
                $settlement->settlement_number = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            }
        });

        static::saving(function (CourierSettlement $settlement) {
            $settlement->balance_amount = round((float) $settlement->net_amount - (float) $settlement->paid_amount, 2);
        });
    }

    public function employee()
    {
        return $this->belongsTo(BranchEmployee::class, 'branch_employee_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function items()
    {
        return $this->hasMany(CourierSettlementItem::class, 'courier_settlement_id');
    }
}
