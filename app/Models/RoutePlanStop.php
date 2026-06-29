<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoutePlanStop extends Model
{
    use HasFactory;

    public $table = 'route_plan_stops';

    const STOP_TYPE = [
        'origin' => 'نقطة الانطلاق',
        'pickup' => 'استلام',
        'hub' => 'مركز تجميع',
        'delivery' => 'تسليم',
        'destination' => 'الوجهة',
        'custom' => 'نقطة مخصصة',
    ];

    protected $fillable = [
        'route_plan_id',
        'stop_order',
        'stop_type',
        'branch_id',
        'city_id',
        'stop_name',
        'latitude',
        'longitude',
        'service_window_from',
        'service_window_to',
        'notes',
    ];

    public function routePlan()
    {
        return $this->belongsTo(RoutePlan::class, 'route_plan_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }
}