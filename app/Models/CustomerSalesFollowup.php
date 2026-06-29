<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerSalesFollowup extends Model
{
    public $table = 'customer_sales_followups';

    const TASK_TYPES = [
        'customer_followup' => 'متابعة عميل',
        'lead_followup' => 'متابعة عميل محتمل',
        'subscription_followup' => 'متابعة اشتراك',
        'contract_followup' => 'متابعة عقد',
        'payment_followup' => 'متابعة دفعات آجلة',
    ];

    const SETTLEMENT_STATUSES = [
        'pending' => 'معلق',
        'approved' => 'معتمد',
        'paid' => 'مدفوع',
        'disputed' => 'متنازع عليه',
    ];

    protected $fillable = [
        'user_id',
        'sales_user_id',
        'assigned_branch_id',
        'assigned_governorate_id',
        'assigned_city_id',
        'related_type',
        'related_id',
        'followup_date',
        'status',
        'channel',
        'task_type',
        'summary',
        'next_action',
        'next_followup_at',
        'target_year',
        'target_month',
        'target_amount',
        'achieved_amount',
        'commission_due',
        'incentive_due',
        'settlement_status',
        'assignment_note',
        'created_by',
    ];

    protected $casts = [
        'followup_date' => 'date',
        'next_followup_at' => 'datetime',
        'target_amount' => 'decimal:2',
        'achieved_amount' => 'decimal:2',
        'commission_due' => 'decimal:2',
        'incentive_due' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function salesUser()
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'assigned_branch_id');
    }

    public function governorate()
    {
        return $this->belongsTo(Governorate::class, 'assigned_governorate_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'assigned_city_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
