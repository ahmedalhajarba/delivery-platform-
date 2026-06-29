<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    public $table = 'vehicles';

    const VEHICLE_TYPES = [
        'motorcycle'   => 'دراجة نارية',
        'car'          => 'سيارة',
        'pickup'       => 'بيك أب',
        'van'          => 'فان',
        'small_truck'  => 'شاحنة صغيرة',
        'large_truck'  => 'شاحنة كبيرة',
    ];

    const STATUS = [
        'available'   => 'متاحة',
        'in_use'      => 'في الخدمة',
        'maintenance' => 'في الصيانة',
        'retired'     => 'متقاعدة',
    ];

    protected $fillable = [
        'plate_number', 'vehicle_type', 'brand', 'model',
        'manufacture_year', 'color', 'max_weight_kg', 'max_volume_m3',
        'branch_id', 'status', 'insurance_expiry', 'registration_expiry',
        'last_maintenance', 'notes',
    ];

    protected $casts = [
        'insurance_expiry'    => 'date',
        'registration_expiry' => 'date',
        'last_maintenance'    => 'date',
        'max_weight_kg'       => 'decimal:2',
        'max_volume_m3'       => 'decimal:3',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function couriers()
    {
        return $this->hasMany(Courier::class, 'vehicle_id');
    }

    public function assignments()
    {
        return $this->hasMany(CourierAssignment::class, 'vehicle_id');
    }

    public function getTypeNameAttribute(): string
    {
        return self::VEHICLE_TYPES[$this->vehicle_type] ?? $this->vehicle_type;
    }

    public function isInsuranceExpiringSoon(): bool
    {
        return $this->insurance_expiry && $this->insurance_expiry->diffInDays(now()) <= 30;
    }
}
