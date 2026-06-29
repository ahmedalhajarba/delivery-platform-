<?php

namespace App\Models;

use DateTimeInterface;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Receipt extends Model
{
    // تم حذف الربط بقسم التمويل الاستراتيجي
    use SoftDeletes, Auditable, HasFactory;

    public $table = 'receipts';

    const PAYMENT_METHOD = [
        'cash'          => 'نقد',
        'bank_transfer' => 'تحويل بنكي',
        'cheque'        => 'شيك',
        'online'        => 'دفع إلكتروني',
        'cod'           => 'دفع عند الاستلام',
    ];

    const STATUS = [
        'confirmed' => 'مؤكد',
        'cancelled' => 'ملغي',
    ];

    protected $fillable = [
        'receipt_number',
        'invoice_id',
        'source_type',
        'source_id',
        'source_event',
        'cash_box_id',
        'received_by',
        'user_id',
        'amount',
        'payment_method',
        'receipt_date',
        'reference_number',
        'bank_name',
        'notes',
        'status',
        'affects_invoice_balance',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'receipt_date' => 'date',
        'affects_invoice_balance' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($receipt) {
            if (empty($receipt->receipt_number)) {
                $receipt->receipt_number = self::generateReceiptNumber();
            }
        });

        // عند تأكيد سند القبض: تحديث الفاتورة والصندوق
        static::created(function ($receipt) {
            if ($receipt->status === 'confirmed') {
                // تحديث المبلغ المدفوع في الفاتورة
                $invoice = $receipt->invoice;
                if ($invoice && $receipt->affects_invoice_balance) {
                    $invoice->increment('paid_amount', $receipt->amount);
                }

                // إيداع في الصندوق
                if ($receipt->cashBox) {
                    $invoiceNumber = $invoice?->invoice_number ?? '-';
                    $receipt->cashBox->deposit(
                        $receipt->amount,
                        'قبض فاتورة رقم: ' . $invoiceNumber,
                        $receipt->id,
                        $receipt->received_by
                    );
                }
            }
        });

        // عند إلغاء سند القبض: عكس العملية
        static::updating(function ($receipt) {
            if ($receipt->isDirty('status') && $receipt->status === 'cancelled') {
                $invoice = $receipt->invoice;
                if ($invoice && $receipt->affects_invoice_balance) {
                    $invoice->decrement('paid_amount', $receipt->amount);
                }
                if ($receipt->cashBox) {
                    $receipt->cashBox->withdraw(
                        $receipt->amount,
                        'إلغاء سند قبض رقم: ' . $receipt->receipt_number,
                        auth()->id()
                    );
                }
            }
        });
    }

    public static function generateReceiptNumber(): string
    {
        $prefix = 'RCP-' . date('Ym') . '-';
        $last   = self::withTrashed()
                      ->where('receipt_number', 'like', $prefix . '%')
                      ->orderByDesc('id')
                      ->first();
        $seq = $last ? (intval(substr($last->receipt_number, strlen($prefix))) + 1) : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    // ========== Relationships ==========

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function cashBox()
    {
        return $this->belongsTo(CashBox::class, 'cash_box_id');
    }

    public function receivedByEmployee()
    {
        return $this->belongsTo(BranchEmployee::class, 'received_by');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cashBoxTransaction()
    {
        return $this->hasOne(CashBoxTransaction::class, 'receipt_id');
    }

    // ========== Accessors ==========

    public function getPaymentMethodLabelAttribute(): string
    {
        return self::PAYMENT_METHOD[$this->payment_method] ?? $this->payment_method;
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
