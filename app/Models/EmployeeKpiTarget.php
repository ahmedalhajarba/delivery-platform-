<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeKpiTarget extends Model
{
    protected $fillable = [
        'user_id',
        'year',
        'month',
        'target_tasks',
        'target_tickets',
        'target_orders',
        'target_followups',
        'target_revenue',
        'notes',
        'set_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function setBy()
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
