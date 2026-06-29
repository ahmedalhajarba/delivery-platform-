<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeStatusLog extends Model
{
    protected $fillable = [
        'user_id',
        'old_status',
        'new_status',
        'note',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
