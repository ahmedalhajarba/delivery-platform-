<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountActivationRequest extends Model
{
    use SoftDeletes;

    public $table = 'account_activation_requests';

    const STATUS_LABELS = [
        'pending'           => 'بانتظار المراجعة',
        'documents_review'  => 'مراجعة المستندات',
        'sales_approved'    => 'موافقة المبيعات',
        'finance_approved'  => 'موافقة المالية',
        'ops_approved'      => 'موافقة التشغيل',
        'activated'         => 'تم التفعيل',
        'rejected'          => 'مرفوض',
    ];

    const STATUS_COLORS = [
        'pending'           => 'secondary',
        'documents_review'  => 'info',
        'sales_approved'    => 'primary',
        'finance_approved'  => 'warning',
        'ops_approved'      => 'orange',
        'activated'         => 'success',
        'rejected'          => 'danger',
    ];

    const APPROVAL_FLOW = [
        ['step_order' => 1, 'step_role' => 'sales_manager',   'step_label' => 'موافقة مدير المبيعات'],
        ['step_order' => 2, 'step_role' => 'finance_manager',  'step_label' => 'موافقة مدير المالية'],
        ['step_order' => 3, 'step_role' => 'ops_manager',      'step_label' => 'موافقة مدير التشغيل'],
    ];

    protected $fillable = [
        'user_id', 'contract_id', 'assigned_to', 'status', 'rejection_reason',
        'activated_at', 'commercial_register', 'tax_number', 'id_document', 'notes',
    ];

    protected $casts = ['activated_at' => 'datetime'];

    public function user()         { return $this->belongsTo(User::class, 'user_id'); }
    public function contract()     { return $this->belongsTo(Contract::class); }
    public function assignedTo()   { return $this->belongsTo(User::class, 'assigned_to'); }
    public function approvalSteps(){ return $this->hasMany(ActivationApprovalStep::class, 'request_id')->orderBy('step_order'); }
    public function documents()    { return $this->hasMany(ContractDocument::class, 'activation_request_id'); }

    public function getStatusLabelAttribute() { return self::STATUS_LABELS[$this->status] ?? $this->status; }
    public function getStatusColorAttribute() { return self::STATUS_COLORS[$this->status] ?? 'secondary'; }

    public function getCurrentStepAttribute()
    {
        return $this->approvalSteps()->where('status', 'pending')->first();
    }

    public function initApprovalSteps()
    {
        foreach (self::APPROVAL_FLOW as $step) {
            $this->approvalSteps()->create(array_merge($step, ['status' => 'pending']));
        }
    }
}
