<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    use HasFactory;

    public $table = 'support_tickets';

    const CATEGORY = [
        'shipment_issue' => 'مشكلة شحنة',
        'delay' => 'تأخير',
        'damage' => 'تلف',
        'lost' => 'فقدان',
        'tracking' => 'استفسار تتبع',
        'additional_service' => 'خدمة إضافية',
        'billing' => 'مشكلة مالية',
        'subscription_issue' => 'طلبات الاشتراكات',
        'sales_customer_issue' => 'طلبات عملاء المبيعات',
        'discount_code_request' => 'طلبات الأكواد والخصومات',
        'deferred_payment_request' => 'طلبات حسابات الدفع الآجل',
        'marketing_campaign_request' => 'طلبات الحملات التسويقية',
        'commission_settlement' => 'مقاصة العمولات والحوافز',
        'target_review' => 'مراجعة التارقت الشهري',
        'other' => 'أخرى',
    ];

    const REQUESTER_DEPARTMENTS = [
        'sales' => 'المبيعات',
        'marketing' => 'التسويق',
        'finance' => 'المالية',
        'operations' => 'العمليات',
        'customer_service' => 'خدمة العملاء',
        'other' => 'أخرى',
    ];

    const REQUEST_CONTEXTS = [
        'subscription' => 'اشتراكات',
        'customer_profile' => 'ملف عميل',
        'discount_code' => 'كود خصم',
        'deferred_account' => 'حساب دفع آجل',
        'campaign' => 'حملة تسويقية',
        'commission' => 'عمولات وحوافز',
        'target' => 'تارقت شهري',
        'other' => 'أخرى',
    ];

    const PRIORITY = [
        'low' => 'منخفضة',
        'normal' => 'عادية',
        'high' => 'مرتفعة',
        'urgent' => 'عاجلة',
    ];

    const STATUS = [
        'open' => 'مفتوحة',
        'in_progress' => 'قيد المعالجة',
        'pending_customer' => 'بانتظار العميل',
        'resolved' => 'تم الحل',
        'closed' => 'مغلقة',
        'cancelled' => 'ملغاة',
    ];

    protected $fillable = [
        'ticket_number',
        'user_id',
        'order_id',
        'waybill_number',
        'subject',
        'category',
        'priority',
        'status',
        'source',
        'requester_department',
        'request_context',
        'additional_service_requested',
        'assigned_to',
        'sales_user_id',
        'campaign_id',
        'sales_lead_id',
        'user_subscription_id',
        'last_reply_at',
        'resolved_at',
        'description',
        'notes',
    ];

    protected $casts = [
        'additional_service_requested' => 'boolean',
        'last_reply_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (SupportTicket $ticket) {
            if (!$ticket->ticket_number) {
                $ticket->ticket_number = 'TCK-' . now()->format('YmdHis');
            }
        });
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function salesUser()
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }

    public function campaign()
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }

    public function salesLead()
    {
        return $this->belongsTo(SalesLead::class, 'sales_lead_id');
    }

    public function subscription()
    {
        return $this->belongsTo(UserSubscription::class, 'user_subscription_id');
    }

    public function messages()
    {
        return $this->hasMany(SupportTicketMessage::class, 'support_ticket_id')->latest();
    }
}