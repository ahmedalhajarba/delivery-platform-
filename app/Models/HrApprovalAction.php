<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrApprovalAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_request_id',
        'workflow_step_id',
        'actor_user_id',
        'action',
        'note',
    ];

    public function request()
    {
        return $this->belongsTo(HrApprovalRequest::class, 'approval_request_id');
    }

    public function step()
    {
        return $this->belongsTo(HrApprovalWorkflowStep::class, 'workflow_step_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
