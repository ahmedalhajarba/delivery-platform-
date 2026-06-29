<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractApproval extends Model
{
    public $table = 'contract_approvals';

    const ACTION_LABELS = [
        'submitted'          => 'تم الإرسال للمراجعة',
        'approved'           => 'موافق عليه',
        'rejected'           => 'مرفوض',
        'suspended'          => 'موقوف',
        'terminated'         => 'منهي',
        'reactivated'        => 'أعيد تفعيله',
    ];

    protected $fillable = ['contract_id', 'user_id', 'action', 'comment'];

    public function contract() { return $this->belongsTo(Contract::class); }
    public function user()     { return $this->belongsTo(User::class); }
    public function getActionLabelAttribute() { return self::ACTION_LABELS[$this->action] ?? $this->action; }
}
