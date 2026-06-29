<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingCampaign extends Model
{
    use SoftDeletes;

    public $table = 'marketing_campaigns';

    const TYPES = [
        'awareness'       => 'توعية بالعلامة التجارية',
        'lead_generation' => 'توليد عملاء محتملين',
        'retention'       => 'الاحتفاظ بالعملاء',
        'upsell'          => 'بيع إضافي / ترقية',
        'event'           => 'حدث / فعالية',
        'seasonal'        => 'موسمية / عروض',
        'referral'        => 'إحالات',
    ];

    const CHANNELS = [
        'email'        => 'بريد إلكتروني',
        'whatsapp'     => 'واتساب',
        'sms'          => 'رسائل SMS',
        'social_media' => 'وسائل التواصل الاجتماعي',
        'phone'        => 'مكالمات هاتفية',
        'event'        => 'فعاليات وأحداث',
        'mixed'        => 'متعدد القنوات',
    ];

    const STATUSES = [
        'planning'   => 'تخطيط',
        'scheduled'  => 'مجدولة',
        'active'     => 'نشطة',
        'paused'     => 'موقوفة مؤقتاً',
        'completed'  => 'مكتملة',
        'cancelled'  => 'ملغاة',
    ];

    const STATUS_COLORS = [
        'planning'   => 'secondary',
        'scheduled'  => 'info',
        'active'     => 'success',
        'paused'     => 'warning',
        'completed'  => 'primary',
        'cancelled'  => 'danger',
    ];

    const ACTIVITY_TYPES = [
        'note'           => 'ملاحظة',
        'email_sent'     => 'إرسال بريد إلكتروني',
        'whatsapp_sent'  => 'إرسال رسالة واتساب',
        'call'           => 'مكالمة هاتفية',
        'event'          => 'فعالية',
        'follow_up'      => 'متابعة',
        'result_update'  => 'تحديث نتائج',
        'status_change'  => 'تغيير حالة',
    ];

    protected $fillable = [
        'campaign_code', 'name', 'campaign_type', 'channel', 'status',
        'description', 'goals', 'target_audience',
        'start_date', 'end_date', 'budget', 'actual_spend',
        'assigned_to', 'created_by',
        'target_leads', 'target_conversions', 'target_revenue',
        'actual_leads', 'actual_conversions', 'actual_revenue',
        'notes',
    ];

    protected $casts = [
        'start_date'       => 'date',
        'end_date'         => 'date',
        'budget'           => 'decimal:2',
        'actual_spend'     => 'decimal:2',
        'target_revenue'   => 'decimal:2',
        'actual_revenue'   => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->campaign_code)) {
                $model->campaign_code = 'CMP-' . date('Ym') . '-' . str_pad(
                    (MarketingCampaign::withTrashed()->whereYear('created_at', date('Y'))->whereMonth('created_at', date('m'))->count() + 1),
                    4, '0', STR_PAD_LEFT
                );
            }
        });
    }

    public function assignedTo()   { return $this->belongsTo(User::class, 'assigned_to'); }
    public function creator()      { return $this->belongsTo(User::class, 'created_by'); }
    public function activities()   { return $this->hasMany(MarketingCampaignActivity::class, 'campaign_id')->orderByDesc('activity_at'); }
    public function leads()        { return $this->belongsToMany(SalesLead::class, 'campaign_sales_lead', 'campaign_id', 'sales_lead_id')->withTimestamps(); }

    public function getStatusLabelAttribute()  { return self::STATUSES[$this->status] ?? $this->status; }
    public function getStatusColorAttribute()  { return self::STATUS_COLORS[$this->status] ?? 'secondary'; }
    public function getTypeLabel()             { return self::TYPES[$this->campaign_type] ?? $this->campaign_type; }
    public function getChannelLabel()          { return self::CHANNELS[$this->channel] ?? $this->channel; }

    public function getLeadProgressAttribute()
    {
        if ($this->target_leads <= 0) return 0;
        return min(100, round(($this->actual_leads / $this->target_leads) * 100));
    }

    public function getConversionRateAttribute()
    {
        if ($this->actual_leads <= 0) return 0;
        return round(($this->actual_conversions / $this->actual_leads) * 100, 1);
    }

    public function getRoiAttribute()
    {
        if ($this->actual_spend <= 0) return null;
        return round((($this->actual_revenue - $this->actual_spend) / $this->actual_spend) * 100, 1);
    }
}
