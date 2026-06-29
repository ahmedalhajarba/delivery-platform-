<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ServicePurchase extends Model
{
    use SoftDeletes;

    public $table = 'service_purchases';

    protected $fillable = [
        'user_id',
        'purchase_number',
        'service_type',
        'subscription_plan_id',
        'quotation_id',
        'order_id',
        'invoice_id',
        'receipt_id',
        'cash_box_id',
        'service_name_ar',
        'service_name_en',
        'service_description',
        'service_ref',
        'unit_price',
        'extra_services_amount',
        'subtotal',
        'tax_enabled',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'currency',
        'selected_extras',
        'status',
        'payment_method',
        'bank_name',
        'bank_iban',
        'bank_account_number',
        'transfer_reference',
        'transfer_receipt_path',
        'paid_at',
        'confirmed_at',
        'confirmed_by',
        'admin_notes',
        'user_notes',
    ];

    protected $casts = [
        'unit_price'             => 'decimal:2',
        'extra_services_amount'  => 'decimal:2',
        'subtotal'               => 'decimal:2',
        'tax_enabled'            => 'boolean',
        'tax_rate'               => 'decimal:2',
        'tax_amount'             => 'decimal:2',
        'total_amount'           => 'decimal:2',
        'selected_extras'        => 'array',
        'paid_at'                => 'datetime',
        'confirmed_at'           => 'datetime',
    ];

    // ── Status labels ─────────────────────────────────────────

    const STATUS_LABELS = [
        'pending'   => ['label' => 'بانتظار الدفع',   'badge' => 'warning'],
        'reviewing' => ['label' => 'قيد المراجعة',    'badge' => 'info'],
        'confirmed' => ['label' => 'مؤكد',             'badge' => 'success'],
        'rejected'  => ['label' => 'مرفوض',            'badge' => 'danger'],
        'cancelled' => ['label' => 'ملغى',             'badge' => 'secondary'],
    ];

    const SERVICE_TYPE_LABELS = [
        'subscription'  => 'اشتراك',
        'single_order'  => 'طلب فردي',
        'extra_service' => 'خدمة إضافية',
        'international' => 'شحن دولي',
    ];

    // ── Helpers ───────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status]['label'] ?? $this->status;
    }

    public function getStatusBadgeAttribute(): string
    {
        return self::STATUS_LABELS[$this->status]['badge'] ?? 'secondary';
    }

    public function getServiceTypeLabelAttribute(): string
    {
        return self::SERVICE_TYPE_LABELS[$this->service_type] ?? $this->service_type;
    }

    // ── Auto-generate purchase number ─────────────────────────

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->purchase_number)) {
                $year  = now()->format('Y');
                $count = self::whereYear('created_at', $year)->count() + 1;
                $model->purchase_number = 'SP-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionsPlan::class, 'subscription_plan_id');
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function receipt()
    {
        return $this->belongsTo(Receipt::class, 'receipt_id');
    }

    public function cashBox()
    {
        return $this->belongsTo(CashBox::class, 'cash_box_id');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
