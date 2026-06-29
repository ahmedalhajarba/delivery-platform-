<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeePayroll extends Model
{
    use HasFactory;

    const STATUS = [
        'draft' => 'مسودة',
        'approved' => 'معتمدة',
        'paid' => 'مدفوعة',
        'cancelled' => 'ملغاة',
    ];

    protected $fillable = [
        'payroll_number',
        'user_id',
        'payroll_month',
        'basic_salary',
        'insurance_amount',
        'allowances_amount',
        'bonuses_amount',
        'incentives_amount',
        'scheduled_deductions_amount',
        'manual_deductions_amount',
        'total_deductions_amount',
        'gross_amount',
        'net_amount',
        'status',
        'created_by',
        'approved_by',
        'paid_by',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'payroll_month' => 'date',
        'paid_at' => 'datetime',
        'basic_salary' => 'decimal:2',
        'insurance_amount' => 'decimal:2',
        'allowances_amount' => 'decimal:2',
        'bonuses_amount' => 'decimal:2',
        'incentives_amount' => 'decimal:2',
        'scheduled_deductions_amount' => 'decimal:2',
        'manual_deductions_amount' => 'decimal:2',
        'total_deductions_amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (EmployeePayroll $payroll) {
            if (empty($payroll->payroll_number)) {
                $prefix = 'PR-' . now()->format('Ym') . '-';
                $last = self::query()
                    ->where('payroll_number', 'like', $prefix . '%')
                    ->latest('id')
                    ->first();

                $seq = $last ? ((int) substr($last->payroll_number, strlen($prefix)) + 1) : 1;
                $payroll->payroll_number = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
