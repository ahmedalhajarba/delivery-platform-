<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use SoftDeletes;

    public $table = 'quotations';

    const STATUS_LABELS = [
        'draft'       => 'مسودة',
        'sent'        => 'مرسل',
        'negotiating' => 'قيد التفاوض',
        'approved'    => 'موافق عليه',
        'rejected'    => 'مرفوض',
        'expired'     => 'منتهي الصلاحية',
        'converted'   => 'تحوّل إلى عقد',
    ];

    const DEAL_STAGES = [
        'prospecting'      => 'استكشاف',
        'qualification'    => 'تأهيل',
        'needs_analysis'   => 'تحليل الاحتياجات',
        'proposal'         => 'تقديم العرض',
        'negotiation'      => 'تفاوض',
        'verbal_approval'  => 'موافقة شفهية',
        'contract_sent'    => 'عقد مُرسل',
        'closed_won'       => 'مغلقة - رابح',
        'closed_lost'      => 'مغلقة - خاسر',
        'on_hold'          => 'معلّقة',
    ];

    const DEAL_STAGE_COLORS = [
        'prospecting'      => 'secondary',
        'qualification'    => 'info',
        'needs_analysis'   => 'primary',
        'proposal'         => 'warning',
        'negotiation'      => 'warning',
        'verbal_approval'  => 'success',
        'contract_sent'    => 'success',
        'closed_won'       => 'success',
        'closed_lost'      => 'danger',
        'on_hold'          => 'dark',
    ];

    const DEAL_STAGE_PROGRESS = [
        'prospecting'      => 10,
        'qualification'    => 20,
        'needs_analysis'   => 35,
        'proposal'         => 50,
        'negotiation'      => 65,
        'verbal_approval'  => 80,
        'contract_sent'    => 90,
        'closed_won'       => 100,
        'closed_lost'      => 0,
        'on_hold'          => 40,
    ];

    const STATUS_COLORS = [
        'draft'       => 'secondary',
        'sent'        => 'primary',
        'negotiating' => 'warning',
        'approved'    => 'success',
        'rejected'    => 'danger',
        'expired'     => 'dark',
        'converted'   => 'info',
    ];

    protected $fillable = [
        'quotation_number', 'user_id', 'created_by', 'status',
        'valid_until', 'notes', 'internal_notes',
        'discount_percent', 'has_deferred_payment', 'deferred_days', 'credit_limit',
        'deal_stage', 'deal_value', 'deal_probability', 'expected_close_date',
        'lost_reason', 'lost_to_competitor', 'sales_lead_id', 'campaign_id',
        'stage_changed_at', 'stage_changed_by',
    ];

    protected $casts = [
        'has_deferred_payment' => 'boolean',
        'valid_until'          => 'date',
        'expected_close_date'  => 'date',
        'stage_changed_at'     => 'datetime',
        'deal_value'           => 'decimal:2',
        'deal_probability'     => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->quotation_number)) {
                $model->quotation_number = 'QT-' . date('Ymd') . '-' . str_pad(
                    (Quotation::whereDate('created_at', today())->count() + 1), 4, '0', STR_PAD_LEFT
                );
            }
        });
    }

    public function user()        { return $this->belongsTo(User::class, 'user_id'); }
    public function createdBy()   { return $this->belongsTo(User::class, 'created_by'); }
    public function stageChangedBy() { return $this->belongsTo(User::class, 'stage_changed_by'); }
    public function pricingLines(){ return $this->hasMany(QuotationPricingLine::class); }
    public function extraServices(){ return $this->hasMany(QuotationExtraService::class); }
    public function approvals()   { return $this->hasMany(QuotationApproval::class); }
    public function dealActivities() { return $this->hasMany(QuotationActivity::class)->orderByDesc('activity_at'); }
    public function documents()   { return $this->hasMany(ContractDocument::class); }
    public function contract()    { return $this->hasOne(Contract::class); }
    public function salesLead()   { return $this->belongsTo(SalesLead::class, 'sales_lead_id'); }
    public function campaign()    { return $this->belongsTo(MarketingCampaign::class, 'campaign_id'); }

    public static function getStatusLabels(): array
    {
        return [
            'draft' => __('quotations.statuses.draft'),
            'sent' => __('quotations.statuses.sent'),
            'negotiating' => __('quotations.statuses.negotiating'),
            'approved' => __('quotations.statuses.approved'),
            'rejected' => __('quotations.statuses.rejected'),
            'expired' => __('quotations.statuses.expired'),
            'converted' => __('quotations.statuses.converted'),
        ];
    }

    public static function getDealStageLabels(): array
    {
        return [
            'prospecting' => __('quotations.deal_stages.prospecting'),
            'qualification' => __('quotations.deal_stages.qualification'),
            'needs_analysis' => __('quotations.deal_stages.needs_analysis'),
            'proposal' => __('quotations.deal_stages.proposal'),
            'negotiation' => __('quotations.deal_stages.negotiation'),
            'verbal_approval' => __('quotations.deal_stages.verbal_approval'),
            'contract_sent' => __('quotations.deal_stages.contract_sent'),
            'closed_won' => __('quotations.deal_stages.closed_won'),
            'closed_lost' => __('quotations.deal_stages.closed_lost'),
            'on_hold' => __('quotations.deal_stages.on_hold'),
        ];
    }

    public function getStatusLabelAttribute() { return self::getStatusLabels()[$this->status] ?? $this->status; }
    public function getStatusColorAttribute() { return self::STATUS_COLORS[$this->status] ?? 'secondary'; }
    public function getDealStageLabelAttribute() { return self::getDealStageLabels()[$this->deal_stage] ?? $this->deal_stage; }
    public function getDealStageColorAttribute() { return self::DEAL_STAGE_COLORS[$this->deal_stage] ?? 'secondary'; }
    public function getDealStageProgressAttribute() { return self::DEAL_STAGE_PROGRESS[$this->deal_stage] ?? 0; }
    public function isExpired() { return $this->valid_until && $this->valid_until->isPast(); }
}
