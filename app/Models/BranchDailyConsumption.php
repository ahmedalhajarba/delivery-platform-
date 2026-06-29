<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchDailyConsumption extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'date',
        'subscription_used',
        'regular_used',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function getTotalUsedAttribute()
    {
        return (int) $this->subscription_used + (int) $this->regular_used;
    }
}
