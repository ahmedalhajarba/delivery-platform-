<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryAttempt extends Model
{
    public $table = 'delivery_attempts';

    public const RESULTS = [
        'no_answer'       => 'لا يوجد رد',
        'wrong_address'   => 'عنوان خاطئ',
        'customer_absent' => 'الزبون غائب',
        'postponed'       => 'مؤجل بطلب الزبون',
        'delivered'       => 'تم التسليم',
        'returned'        => 'أُعيد',
    ];

    public const RESULT_COLORS = [
        'no_answer'       => 'warning',
        'wrong_address'   => 'danger',
        'customer_absent' => 'secondary',
        'postponed'       => 'info',
        'delivered'       => 'success',
        'returned'        => 'dark',
    ];

    protected $fillable = [
        'order_id',
        'attempt_number',
        'result',
        'notes',
        'attempt_cost',
        'courier_id',
        'attempted_at',
    ];

    protected $casts = [
        'attempt_cost' => 'decimal:2',
        'attempted_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function courier()
    {
        return $this->belongsTo(BranchEmployee::class, 'courier_id');
    }

    public function getResultLabelAttribute(): string
    {
        return self::RESULTS[$this->result] ?? $this->result;
    }

    public function getResultColorAttribute(): string
    {
        return self::RESULT_COLORS[$this->result] ?? 'secondary';
    }
}
