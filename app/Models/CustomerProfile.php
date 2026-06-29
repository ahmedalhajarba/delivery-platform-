<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerProfile extends Model
{
    use SoftDeletes;

    public $table = 'customer_profiles';

    const CUSTOMER_TYPES = [
        'individual' => 'فرد',
        'company'    => 'شركة',
        'enterprise' => 'مؤسسة/كيان كبير',
    ];

    const BILLING_TYPES = [
        'direct'       => 'دفع مباشر',
        'subscription' => 'اشتراك',
        'deferred'     => 'آجل',
    ];

    const ACCOUNT_STATUS = [
        'pending'   => 'قيد الانتظار',
        'active'    => 'نشط',
        'suspended' => 'موقوف مؤقتاً',
        'frozen'    => 'مجمّد',
        'blocked'   => 'محظور',
    ];

    const ACCOUNT_STATUS_COLORS = [
        'pending'   => 'warning',
        'active'    => 'success',
        'suspended' => 'orange',
        'frozen'    => 'info',
        'blocked'   => 'danger',
    ];

    const PRIORITY = [
        'normal'  => 'عادي',
        'vip'     => 'VIP',
        'premium' => 'بريميوم',
    ];

    protected $fillable = [
        'user_id', 'company_name', 'tax_number', 'commercial_register', 'national_id',
        'customer_type', 'billing_type', 'customer_code', 'phone2', 'whatsapp', 'website',
        'contact_person', 'contact_person_mobile', 'address_line1', 'address_line2',
        'city_id', 'postal_code', 'account_status', 'suspension_reason',
        'suspended_at', 'suspension_lifted_at', 'suspended_by',
        'sales_rep_id', 'account_manager_id', 'priority',
        'credit_balance', 'deferred_balance', 'cod_pending', 'cod_settled',
        'credit_limit', 'credit_used', 'shipment_limit', 'shipments_used',
        'discount_percent', 'special_shipping_rate', 'payment_cycle_days',
        'deferred_approved_by', 'deferred_approved_at',
        'bank_name', 'iban', 'bank_account_holder',
        'total_orders', 'delivered_orders', 'returned_orders', 'pending_orders', 'total_revenue',
        'email_notifications', 'sms_notifications', 'active_contract_id', 'notes',
    ];

    protected $casts = [
        'credit_balance'    => 'decimal:2',
        'deferred_balance'  => 'decimal:2',
        'credit_limit'      => 'decimal:2',
        'credit_used'       => 'decimal:2',
        'discount_percent'  => 'decimal:2',
        'special_shipping_rate' => 'decimal:2',
        'cod_pending'       => 'decimal:2',
        'cod_settled'       => 'decimal:2',
        'total_revenue'     => 'decimal:2',
        'email_notifications' => 'boolean',
        'sms_notifications'   => 'boolean',
        'suspended_at'        => 'datetime',
        'suspension_lifted_at' => 'datetime',
        'deferred_approved_at' => 'datetime',
    ];

    // --- Relations ---

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function salesRep()
    {
        return $this->belongsTo(User::class, 'sales_rep_id');
    }

    public function accountManager()
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function suspendedBy()
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    public function deferredApprovedBy()
    {
        return $this->belongsTo(User::class, 'deferred_approved_by');
    }

    public function activeContract()
    {
        return $this->belongsTo(Contract::class, 'active_contract_id');
    }

    // --- Helpers ---

    public static function generateCode(): string
    {
        $last = static::max('id') ?? 0;
        return 'CUS-' . str_pad($last + 1, 4, '0', STR_PAD_LEFT);
    }

    /**
     * الحصول على تسمية الحالة
     */
    public function getStatusLabelAttribute(): string
    {
        return self::ACCOUNT_STATUS[$this->account_status] ?? ($this->account_status ?? 'غير محدد');
    }

    /**
     * الحصول على لون الحالة
     */
    public function getStatusColorAttribute(): string
    {
        return self::ACCOUNT_STATUS_COLORS[$this->account_status] ?? 'secondary';
    }

    /**
     * الحصول على تسمية الأولوية
     */
    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITY[$this->priority] ?? ($this->priority ?? 'غير محدد');
    }

    /**
     * الحصول على تسمية نوع العميل
     */
    public function getTypeLabelAttribute(): string
    {
        return self::CUSTOMER_TYPES[$this->customer_type] ?? ($this->customer_type ?? 'غير محدد');
    }

    /**
     * الحصول على تسمية نوع الفاتورة
     */
    public function getBillingTypeLabelAttribute(): string
    {
        return self::BILLING_TYPES[$this->billing_type] ?? ($this->billing_type ?? 'غير محدد');
    }

    public function isActive(): bool
    {
        return $this->account_status === 'active';
    }

    public function syncStats(): void
    {
        $userId = $this->user_id;
        $ordersQuery = Order::where('user_id', $userId);
        $deliveredStatusIds = OrderStatus::query()
            ->whereIn('name_en', ['Delivered', 'delivered'])
            ->orWhereIn('name_ar', ['تم التوصيل', 'تم التسليم'])
            ->pluck('id')
            ->toArray();

        if (empty($deliveredStatusIds)) {
            $deliveredStatusIds = [4];
        }

        $this->total_orders = (clone $ordersQuery)->count();
        $this->delivered_orders = (clone $ordersQuery)
            ->whereIn('order_status_id', $deliveredStatusIds)
            ->count();
        $this->returned_orders = (clone $ordersQuery)
            ->whereHas('orderReturn')
            ->count();
        $this->pending_orders = max($this->total_orders - $this->delivered_orders - $this->returned_orders, 0);
        $this->total_revenue = (clone $ordersQuery)->sum('total_cost');
        $this->cod_pending      = CodSettlement::where('user_id', $userId)->whereIn('status', ['pending','processing'])->sum('net_amount');
        $this->cod_settled      = CodSettlement::where('user_id', $userId)->where('status', 'paid')->sum('net_amount');
        $this->shipments_used   = (clone $ordersQuery)->where('order_type', 'deferred')->count();
        $this->syncBillingTypeFromState();
        $this->save();
    }

    public function syncBillingTypeFromState(): void
    {
        $hasActiveSubscription = UserSubscription::query()
            ->where('user_id', $this->user_id)
            ->where('subscription_status', 'active')
            ->exists();

        $hasDeferredActivity = ((float) $this->deferred_balance > 0)
            || ((float) $this->credit_limit > 0)
            || ((int) $this->shipments_used > 0);

        if ($hasDeferredActivity) {
            $this->billing_type = 'deferred';
            return;
        }

        if ($hasActiveSubscription) {
            $this->billing_type = 'subscription';
            return;
        }

        $this->billing_type = 'direct';
    }
}