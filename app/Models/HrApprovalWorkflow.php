<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrApprovalWorkflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'request_type',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function steps()
    {
        return $this->hasMany(HrApprovalWorkflowStep::class, 'workflow_id')->orderBy('step_order');
    }

    public function requests()
    {
        return $this->hasMany(HrApprovalRequest::class, 'workflow_id');
    }
}
