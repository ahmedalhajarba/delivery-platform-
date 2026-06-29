<?php

namespace App\Models;

use DateTimeInterface;
use App\Models\Company;
use App\Models\SalesDiscountCode;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes, Auditable, HasFactory;

    public $table = 'invoices';

    const INVOICE_TYPE = [
        'shipping'     => 'شحن',
        'subscription' => 'اشتراك',
        'cod'          => 'دفع عند الاستلام',
        'deferred'     => 'آجل الدفع',
    ];

    const SOURCE_TYPE = [
        'user_subscription' => 'اشتراك مستخدم',
        'subscription_extra_charge' => 'رسوم إضافية',
        'service_purchase' => 'شراء خدمة منصة',
    ];

    const STATUS = [
        'draft'           => 'مسودة',
        'issued'          => 'صادرة',
        'partially_paid'  => 'مدفوعة جزئياً',
        'paid'            => 'مدفوعة',
        'cancelled'       => 'ملغية',
        'overdue'         => 'متأخرة',
    ];

    const STATUS_COLORS = [
        'draft'           => 'secondary',
        'issued'          => 'primary',
        'partially_paid'  => 'info',
        'paid'            => 'success',
        'cancelled'       => 'dark',
        'overdue'         => 'danger',
    ];

    protected $fillable = [
        'invoice_number',
        'invoice_type',
        'source_type',
        'source_id',
        'source_event',
        'sales_discount_code_id',
        'status',
        'user_id',
        'sales_owner_id',
        'client_name',
        'client_phone',
        'client_address',
        'subtotal',
        'discount_amount',
        'sales_discount_amount',
        'finance_discount_amount',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'issue_date',
        'due_date',
        'paid_date',
        'billing_period',
        'period_from',
        'period_to',
        'branch_id',
        'notes',
        'bank_name_snapshot',
        'bank_account_name_snapshot',
        'iban_snapshot',
        'account_number_snapshot',
        'swift_code_snapshot',
        'bank_branch_snapshot',
        'payment_instructions_snapshot',
        'pdf_path',
    ];

    protected $dates = ['issue_date', 'due_date', 'paid_date', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'subtotal'         => 'decimal:2',
        'discount_amount'  => 'decimal:2',
        'sales_discount_amount'  => 'decimal:2',
        'finance_discount_amount'  => 'decimal:2',
        'tax_amount'       => 'decimal:2',
        'total_amount'     => 'decimal:2',
        'paid_amount'      => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'period_from' => 'date',
        'period_to' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = self::generateInvoiceNumber();
            }
        });

        // تحديث الرصيد المتبقي تلقائياً
        static::saving(function ($invoice) {
            $invoice->remaining_amount = $invoice->total_amount - $invoice->paid_amount;
            if ($invoice->remaining_amount <= 0) {
                $invoice->status = 'paid';
                $invoice->paid_date = now()->toDateString();
            }
        });
    }

    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . date('Ym') . '-';
        $last   = self::withTrashed()
                      ->where('invoice_number', 'like', $prefix . '%')
                      ->orderByDesc('id')
                      ->first();
        $seq = $last ? (intval(substr($last->invoice_number, strlen($prefix))) + 1) : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    // ========== Relationships ==========

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function salesOwner()
    {
        return $this->belongsTo(User::class, 'sales_owner_id');
    }

    public function salesDiscountCode()
    {
        return $this->belongsTo(SalesDiscountCode::class, 'sales_discount_code_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    public function receipts()
    {
        return $this->hasMany(Receipt::class, 'invoice_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'invoice_id');
    }

    // ========== Accessors ==========

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function getInvoiceTypeLabelAttribute(): string
    {
        return self::INVOICE_TYPE[$this->invoice_type] ?? $this->invoice_type;
    }

    public function getSourceTypeLabelAttribute(): string
    {
        return self::SOURCE_TYPE[$this->source_type] ?? ($this->source_type ?: 'عام');
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date && $this->due_date->isPast() && $this->status !== 'paid';
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
