<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesLead extends Model
{
    use SoftDeletes;

    public $table = 'sales_leads';

    const LEAD_TYPES = [
        'company' => 'شركة',
        'ecommerce_store' => 'متجر إلكتروني',
        'individual' => 'فرد',
        'marketplace_seller' => 'بائع منصة',
    ];

    const STATUSES = [
        'cold' => 'بارد',
        'warm' => 'دافئ',
        'qualified' => 'مؤهل',
        'proposal' => 'مرحلة عرض سعر',
        'negotiation' => 'تفاوض',
        'won' => 'تحول إلى زبون',
        'lost' => 'مفقود',
        'dormant' => 'خامل',
    ];

    const STATUS_COLORS = [
        'cold' => 'secondary',
        'warm' => 'warning',
        'qualified' => 'info',
        'proposal' => 'primary',
        'negotiation' => 'dark',
        'won' => 'success',
        'lost' => 'danger',
        'dormant' => 'secondary',
    ];

    protected $fillable = [
        'lead_code',
        'lead_type',
        'qualification_status',
        'lead_source',
        'company_name',
        'store_name',
        'contact_name',
        'contact_job_title',
        'email',
        'mobile',
        'whatsapp',
        'website',
        'tax_number',
        'city_id',
        'address_line1',
        'industry',
        'expected_monthly_shipments',
        'expected_monthly_revenue',
        'expected_average_order_value',
        'assigned_to',
        'created_by',
        'last_contact_at',
        'next_followup_at',
        'qualification_notes',
        'lost_reason',
        'quotation_id',
        'contract_id',
        'user_subscription_id',
        'order_id',
        'converted_user_id',
        'converted_at',
    ];

    protected $casts = [
        'last_contact_at' => 'datetime',
        'next_followup_at' => 'datetime',
        'converted_at' => 'datetime',
        'expected_monthly_shipments' => 'integer',
        'expected_monthly_revenue' => 'decimal:2',
        'expected_average_order_value' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (SalesLead $lead) {
            if (!empty($lead->lead_code)) {
                return;
            }

            $prefix = 'LEAD-' . now()->format('Ym') . '-';
            $todayCount = static::where('lead_code', 'like', $prefix . '%')->count() + 1;
            $lead->lead_code = $prefix . str_pad((string) $todayCount, 4, '0', STR_PAD_LEFT);
        });
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function userSubscription()
    {
        return $this->belongsTo(UserSubscription::class, 'user_subscription_id');
    }

    public function convertedUser()
    {
        return $this->belongsTo(User::class, 'converted_user_id');
    }

    public function activities()
    {
        return $this->hasMany(SalesLeadActivity::class, 'sales_lead_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->qualification_status] ?? $this->qualification_status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->qualification_status] ?? 'secondary';
    }

    public function getLeadTypeLabelAttribute(): string
    {
        return self::LEAD_TYPES[$this->lead_type] ?? $this->lead_type;
    }
}
