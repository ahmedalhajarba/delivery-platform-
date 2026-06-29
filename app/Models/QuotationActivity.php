<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationActivity extends Model
{
    public $table = 'quotation_activities';

    const TYPES = [
        'note'          => 'ملاحظة',
        'call'          => 'مكالمة',
        'meeting'       => 'اجتماع',
        'email'         => 'بريد إلكتروني',
        'whatsapp'      => 'واتساب',
        'stage_change'  => 'تغيير مرحلة الصفقة',
        'follow_up'     => 'متابعة',
        'reminder'      => 'تذكير',
        'revision_sent' => 'إرسال مراجعة',
    ];

    const OUTCOMES = [
        'interested'     => 'مهتم',
        'not_interested' => 'غير مهتم',
        'callback'       => 'طلب معاودة الاتصال',
        'no_answer'      => 'لا يوجد رد',
        'agreed'         => 'موافقة',
        'rejected'       => 'رفض',
    ];

    protected $fillable = [
        'quotation_id', 'activity_type', 'content', 'outcome',
        'activity_at', 'next_followup_at', 'deal_stage_snapshot', 'created_by',
    ];

    protected $casts = [
        'activity_at'      => 'datetime',
        'next_followup_at' => 'datetime',
    ];

    public function quotation() { return $this->belongsTo(Quotation::class); }
    public function creator()   { return $this->belongsTo(User::class, 'created_by'); }

    public function getTypeLabelAttribute()    { return self::TYPES[$this->activity_type] ?? $this->activity_type; }
    public function getOutcomeLabelAttribute() { return self::OUTCOMES[$this->outcome] ?? $this->outcome; }
}
