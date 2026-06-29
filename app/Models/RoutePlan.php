<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoutePlan extends Model
{
    use HasFactory;

    public $table = 'route_plans';

    const TRIP_DIRECTION = [
        'one_way' => 'ذهاب فقط',
        'round_trip' => 'ذهاب وعودة',
    ];

    protected $fillable = [
        'name',
        'code',
        'branch_id',
        'trip_direction',
        'pickup_window_from',
        'pickup_window_to',
        'delivery_window_from',
        'delivery_window_to',
        'estimated_distance_km',
        'estimated_duration_minutes',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function stops()
    {
        return $this->hasMany(RoutePlanStop::class, 'route_plan_id')->orderBy('stop_order');
    }

    public function trips()
    {
        return $this->hasMany(Trip::class, 'route_plan_id');
    }
}