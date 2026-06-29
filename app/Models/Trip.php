<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    use HasFactory;

    public $table = 'trips';

    const TRIP_TYPE = [
        'pickup' => 'استلام',
        'delivery' => 'تسليم',
        'mixed' => 'مختلطة',
        'return' => 'إرجاع',
        'transfer' => 'نقل',
    ];

    const TRIP_DIRECTION = [
        'one_way' => 'ذهاب فقط',
        'round_trip' => 'ذهاب وعودة',
    ];

    const STATUS = [
        'planned' => 'مخططة',
        'assigned' => 'تم الإسناد',
        'in_progress' => 'قيد التنفيذ',
        'completed' => 'مكتملة',
        'cancelled' => 'ملغاة',
    ];

    protected $fillable = [
        'trip_number',
        'route_plan_id',
        'courier_id',
        'vehicle_id',
        'branch_id',
        'created_by',
        'trip_type',
        'trip_direction',
        'status',
        'scheduled_date',
        'pickup_window_from',
        'pickup_window_to',
        'delivery_window_from',
        'delivery_window_to',
        'started_at',
        'completed_at',
        'estimated_shipments_count',
        'actual_shipments_count',
        'estimated_distance_km',
        'actual_distance_km',
        'estimated_cost',
        'actual_cost',
        'fuel_cost',
        'toll_cost',
        'driver_fee',
        'helper_fee',
        'notes',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Trip $trip) {
            if (!$trip->trip_number) {
                $trip->trip_number = 'TRP-' . now()->format('YmdHis');
            }
        });
    }

    public function routePlan()
    {
        return $this->belongsTo(RoutePlan::class, 'route_plan_id');
    }

    public function courier()
    {
        return $this->belongsTo(Courier::class, 'courier_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tripOrders()
    {
        return $this->hasMany(TripOrder::class, 'trip_id')->orderBy('stop_sequence');
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'trip_orders', 'trip_id', 'order_id')->withPivot(['task_type', 'status', 'stop_sequence', 'scheduled_time', 'completed_at', 'notes'])->withTimestamps();
    }

    public function settlements()
    {
        return $this->hasMany(CourierSettlement::class, 'trip_id');
    }

    public function recalculateActualCost(): void
    {
        $this->actual_cost = (float) $this->fuel_cost + (float) $this->toll_cost + (float) $this->driver_fee + (float) $this->helper_fee;
    }
}