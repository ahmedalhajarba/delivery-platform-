<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Courier extends Model
{
    use HasFactory, SoftDeletes;

    public $table = 'couriers';

    const TYPE = [
        'pickup'   => 'مندوب التقاط',
        'delivery' => 'مندوب تسليم',
        'both'     => 'التقاط وتسليم',
    ];

    const STATUS = [
        'active'     => 'نشط',
        'on_leave'   => 'في إجازة',
        'suspended'  => 'موقوف',
        'terminated' => 'منتهي الخدمة',
    ];

    protected $fillable = [
        'name', 'employee_id', 'national_id', 'mobile', 's_mobile',
        'email', 'type', 'branch_id', 'vehicle_id', 'user_id',
        'status', 'is_available', 'latitude', 'longitude',
        'last_location_update', 'max_daily_orders',
        'contract_start', 'contract_end', 'notes', 'photo',
    ];

    protected $casts = [
        'is_available'          => 'boolean',
        'latitude'              => 'decimal:7',
        'longitude'             => 'decimal:7',
        'last_location_update'  => 'datetime',
        'contract_start'        => 'date',
        'contract_end'          => 'date',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignments()
    {
        return $this->hasMany(CourierAssignment::class, 'courier_id');
    }

    public function activeAssignments()
    {
        return $this->hasMany(CourierAssignment::class, 'courier_id')
            ->whereIn('status', ['assigned', 'accepted', 'picked_up']);
    }

    public function trackingRecords()
    {
        return $this->hasMany(ShipmentTracking::class, 'courier_id');
    }

    public function trips()
    {
        return $this->hasMany(Trip::class, 'courier_id');
    }

    public function settlements()
    {
        return $this->hasMany(CourierSettlement::class, 'courier_id');
    }

    public function bookings()
    {
        return $this->hasMany(CourierBooking::class, 'courier_id');
    }

    public function getTypeNameAttribute(): string
    {
        return self::TYPE[$this->type] ?? $this->type;
    }

    public function getStatusNameAttribute(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    // عدد الطلبات النشطة اليوم
    public function todayOrdersCount(): int
    {
        return $this->assignments()
            ->whereDate('assigned_at', today())
            ->whereIn('status', ['assigned', 'accepted', 'picked_up'])
            ->count();
    }
}
