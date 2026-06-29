<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinanceDocument extends Model
{
    use HasFactory;

    const DOCUMENT_TYPE = [
        'expense_invoice' => 'فاتورة مصاريف',
        'purchase_invoice' => 'فاتورة مشتريات',
        'payment_voucher' => 'سند صرف',
    ];

    const STATUS = [
        'draft' => 'مسودة',
        'submitted' => 'مرفوعة',
        'approved' => 'معتمدة',
        'settled' => 'مصفاة',
        'rejected' => 'مرفوضة',
    ];

    const CLEARANCE_STATUS = [
        'none' => 'غير مطبق',
        'pending' => 'بانتظار المخالصة',
        'partial' => 'مخالصة جزئية',
        'cleared' => 'مخالصة كاملة',
    ];

    protected $fillable = [
        'document_number',
        'document_type',
        'branch_id',
        'related_user_id',
        'related_employee_id',
        'beneficiary_name',
        'title',
        'document_date',
        'subtotal',
        'tax_amount',
        'total_amount',
        'status',
        'clearance_status',
        'attachment_path',
        'description',
        'notes',
        'created_by',
        'approved_by',
        'settled_by',
        'settled_at',
    ];

    protected $casts = [
        'document_date' => 'date',
        'settled_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (FinanceDocument $document) {
            if (!empty($document->document_number)) {
                return;
            }

            $prefixMap = [
                'expense_invoice' => 'EXP',
                'purchase_invoice' => 'PUR',
                'payment_voucher' => 'PV',
            ];

            $prefix = ($prefixMap[$document->document_type] ?? 'FD') . '-' . now()->format('Ym') . '-';

            $last = self::query()
                ->where('document_number', 'like', $prefix . '%')
                ->latest('id')
                ->first();

            $seq = $last ? ((int) substr($last->document_number, strlen($prefix)) + 1) : 1;
            $document->document_number = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        });
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function relatedUser()
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }

    public function relatedEmployee()
    {
        return $this->belongsTo(BranchEmployee::class, 'related_employee_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function settler()
    {
        return $this->belongsTo(User::class, 'settled_by');
    }
}
