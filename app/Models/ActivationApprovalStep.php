<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivationApprovalStep extends Model
{
    public $table = 'activation_approval_steps';

    protected $fillable = [
        'request_id', 'approver_id', 'step_role', 'step_label',
        'step_order', 'status', 'comment', 'decided_at',
    ];

    protected $casts = ['decided_at' => 'datetime'];

    public function request()  { return $this->belongsTo(AccountActivationRequest::class, 'request_id'); }
    public function approver() { return $this->belongsTo(User::class, 'approver_id'); }
}
