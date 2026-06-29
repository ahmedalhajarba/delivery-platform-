<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderReturn extends Model
{
    public $table = 'order_returns';

    public const RETURN_REASONS = [
        'no_answer'       => 'عدم رد المتصل',
        'exceeded_days'   => 'تجاوز مدة التخزين (15 يوم)',
        'customer_refused'=> 'رفض الاستلام',
        'wrong_address'   => 'عنوان خاطئ',
        'damaged'         => 'بضاعة تالفة',
        'other'           => 'أخرى',
    ];

    public const STATUSES = [
        'pending'    => 'في الانتظار',
        'in_transit' => 'في الطريق',
        'delivered'  => 'تم التسليم للمرسل',
        'cancelled'  => 'ملغي',
    ];

    public const STATUS_COLORS = [
        'pending'    => 'warning',
        'in_transit' => 'info',
        'delivered'  => 'success',
        'cancelled'  => 'secondary',
    ];

    protected $fillable = [
        'original_order_id',
        'return_reason',
        'return_reason_note',
        'return_cost',
        'return_waybill_number',
        'return_order_id',
        'status',
        'created_by',
    ];

    protected $casts = [
        'return_cost' => 'decimal:2',
    ];

    public function originalOrder()
    {
        return $this->belongsTo(Order::class, 'original_order_id');
    }

    public function returnOrder()
    {
        return $this->belongsTo(Order::class, 'return_order_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getReasonLabelAttribute(): string
    {
        return self::RETURN_REASONS[$this->return_reason] ?? $this->return_reason;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'secondary';
    }
}
