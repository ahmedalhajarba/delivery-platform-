<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeDeduction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'calculation_type',
        'amount',
        'percentage',
        'starts_at',
        'ends_at',
        'is_recurring',
        'is_active',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'is_recurring' => 'boolean',
        'is_active' => 'boolean',
        'amount' => 'decimal:2',
        'percentage' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolveAmount(float $basicSalary): float
    {
        if ($this->calculation_type === 'percentage') {
            $pct = (float) ($this->percentage ?? 0);
            return round(max(0, $basicSalary * $pct / 100), 2);
        }

        return round(max(0, (float) $this->amount), 2);
    }
}
