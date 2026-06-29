<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrApprovalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'request_type',
        'reference_type',
        'reference_id',
        'payload',
        'requested_by',
        'current_step_order',
        'status',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function workflow()
    {
        return $this->belongsTo(HrApprovalWorkflow::class, 'workflow_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function actions()
    {
        return $this->hasMany(HrApprovalAction::class, 'approval_request_id')->latest();
    }

    public function currentStep()
    {
        return $this->workflow?->steps()->where('step_order', $this->current_step_order)->first();
    }
}
