<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TripOrder extends Model
{
    use HasFactory;

    public $table = 'trip_orders';

    const TASK_TYPE = [
        'pickup' => 'استلام',
        'delivery' => 'تسليم',
        'return' => 'إرجاع',
        'exchange' => 'استبدال',
    ];

    const STATUS = [
        'pending' => 'بانتظار التنفيذ',
        'loaded' => 'تم التحميل',
        'in_transit' => 'في الطريق',
        'delivered' => 'تم التنفيذ',
        'failed' => 'فشل التنفيذ',
        'returned' => 'مرتجع',
    ];

    protected $fillable = [
        'trip_id',
        'order_id',
        'stop_sequence',
        'task_type',
        'status',
        'scheduled_time',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'scheduled_time' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class, 'trip_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}