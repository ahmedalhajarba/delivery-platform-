<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashBoxTransaction extends Model
{
    // تم حذف الربط بقسم التمويل الاستراتيجي
    use HasFactory;

    public $table = 'cash_box_transactions';

    const TYPE = [
        'credit'   => 'إيداع',
        'debit'    => 'سحب',
        'transfer' => 'تحويل',
    ];

    protected $fillable = [
        'cash_box_id',
        'receipt_id',
        'transaction_type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'performed_by',
        'transfer_to_box_id',
    ];

    protected $casts = [
        'amount'         => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after'  => 'decimal:2',
    ];

    public function cashBox()
    {
        return $this->belongsTo(CashBox::class, 'cash_box_id');
    }

    public function receipt()
    {
        return $this->belongsTo(Receipt::class, 'receipt_id');
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function transferToBox()
    {
        return $this->belongsTo(CashBox::class, 'transfer_to_box_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE[$this->transaction_type] ?? $this->transaction_type;
    }
}
