<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'module',
        'task_type',
        'priority',
        'status',
        'branch_id',
        'assigned_user_id',
        'assigned_employee_id',
        'created_by',
        'related_type',
        'related_id',
        'due_at',
        'completed_at',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function assignedEmployee()
    {
        return $this->belongsTo(BranchEmployee::class, 'assigned_employee_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function related()
    {
        if (!$this->related_type || !$this->related_id) {
            return null;
        }

        return app($this->related_type)::find($this->related_id);
    }
}
