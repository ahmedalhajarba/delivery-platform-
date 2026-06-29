<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesLeadActivity extends Model
{
    public $table = 'sales_lead_activities';

    const ACTIVITY_TYPES = [
        'note' => 'ملاحظة',
        'call' => 'مكالمة',
        'meeting' => 'اجتماع',
        'email' => 'بريد إلكتروني',
        'whatsapp' => 'واتساب',
        'status_change' => 'تحديث حالة',
        'qualification' => 'تأهيل',
        'conversion' => 'تحويل',
        'linkage' => 'ربط أنظمة',
    ];

    protected $fillable = [
        'sales_lead_id',
        'activity_type',
        'summary',
        'activity_at',
        'next_action',
        'created_by',
    ];

    protected $casts = [
        'activity_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(SalesLead::class, 'sales_lead_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::ACTIVITY_TYPES[$this->activity_type] ?? $this->activity_type;
    }
}
