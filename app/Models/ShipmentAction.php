<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentAction extends Model
{
    use HasFactory;

    public $table = 'shipment_actions';

    const ACTION_TYPES = [
        'hold'              => 'تعليق الشحنة',
        'release'           => 'الإفراج عن الشحنة',
        'redirect'          => 'إعادة توجيه',
        'priority_upgrade'  => 'ترقية الأولوية',
        'partial_return'    => 'إرجاع جزئي',
        'full_return'       => 'إرجاع كامل',
        'reassign_courier'  => 'إعادة تعيين مندوب',
        'transfer_carrier'  => 'نقل لشركة ناقلة',
        'add_note'          => 'إضافة ملاحظة',
        'force_status'      => 'تغيير الحالة يدوياً',
        'schedule_delivery' => 'جدولة موعد تسليم',
        'cancel'            => 'إلغاء الطلب',
    ];

    protected $fillable = [
        'order_id', 'performed_by', 'action_type',
        'action_reason', 'action_data', 'action_at', 'status',
    ];

    protected $casts = [
        'action_at'   => 'datetime',
        'action_data' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function getActionNameAttribute(): string
    {
        return self::ACTION_TYPES[$this->action_type] ?? $this->action_type;
    }
}
