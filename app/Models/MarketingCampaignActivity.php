<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingCampaignActivity extends Model
{
    public $table = 'marketing_campaign_activities';

    protected $fillable = [
        'campaign_id', 'activity_type', 'content',
        'leads_gained', 'conversions_gained', 'revenue_gained', 'spend_recorded',
        'activity_at', 'created_by',
    ];

    protected $casts = [
        'activity_at' => 'datetime',
    ];

    public function campaign() { return $this->belongsTo(MarketingCampaign::class, 'campaign_id'); }
    public function creator()  { return $this->belongsTo(User::class, 'created_by'); }

    public function getTypeLabelAttribute()
    {
        return MarketingCampaign::ACTIVITY_TYPES[$this->activity_type] ?? $this->activity_type;
    }
}
