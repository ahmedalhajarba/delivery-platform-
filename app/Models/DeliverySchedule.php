<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliverySchedule extends Model
{
    use HasFactory;

    public $table = 'delivery_schedules';

    const STATUS = [
        'scheduled'   => 'مجدول',
        'confirmed'   => 'مؤكد',
        'in_progress' => 'قيد التنفيذ',
        'completed'   => 'منجز',
        'rescheduled' => 'أعيد جدولته',
        'cancelled'   => 'ملغي',
    ];

    protected $fillable = [
        'order_id', 'courier_id', 'scheduled_date',
        'time_from', 'time_to', 'status', 'attempt_number',
        'customer_notes', 'courier_notes',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function courier()
    {
        return $this->belongsTo(Courier::class, 'courier_id');
    }
}
