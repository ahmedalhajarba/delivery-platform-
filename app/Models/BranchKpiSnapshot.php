<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchKpiSnapshot extends Model
{
    public $table = 'branch_kpi_snapshots';

    protected $fillable = [
        'branch_id',
        'date',
        'total_orders',
        'delivered_orders',
        'delivery_rate',
        'on_time_rate',
        'total_tickets',
        'resolved_tickets',
        'resolution_rate',
        'fleet_total',
        'fleet_ready',
        'fleet_ready_rate',
        'capacity_utilization_rate',
        'score',
        'grade',
        'score_breakdown',
    ];

    protected $casts = [
        'date' => 'date',
        'score_breakdown' => 'array',
        'delivery_rate' => 'decimal:2',
        'on_time_rate' => 'decimal:2',
        'resolution_rate' => 'decimal:2',
        'fleet_ready_rate' => 'decimal:2',
        'capacity_utilization_rate' => 'decimal:2',
        'score' => 'decimal:2',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
