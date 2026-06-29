<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourierAssignment extends Model
{
    use HasFactory;

    public $table = 'courier_assignments';

    const ASSIGNMENT_TYPE = [
        'pickup'   => 'التقاط',
        'delivery' => 'تسليم',
        'transfer' => 'نقل بين فروع',
        'return'   => 'إرجاع للمرسل',
    ];

    const STATUS = [
        'assigned'   => 'مُعيَّن',
        'accepted'   => 'مقبول',
        'rejected'   => 'مرفوض',
        'picked_up'  => 'تم الالتقاط',
        'delivered'  => 'تم التسليم',
        'failed'     => 'فشل',
        'cancelled'  => 'ملغي',
    ];

    protected $fillable = [
        'order_id', 'courier_id', 'vehicle_id', 'assignment_type',
        'status', 'assigned_at', 'accepted_at', 'completed_at',
        'scheduled_at', 'failure_reason', 'notes', 'assigned_by',
    ];

    protected $casts = [
        'assigned_at'   => 'datetime',
        'accepted_at'   => 'datetime',
        'completed_at'  => 'datetime',
        'scheduled_at'  => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function courier()
    {
        return $this->belongsTo(Courier::class, 'courier_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
