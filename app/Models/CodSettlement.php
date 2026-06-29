<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CodSettlement extends Model
{
    use SoftDeletes;

    public $table = 'cod_settlements';

    protected $fillable = [
        'user_id',
        'settlement_number',
        'gross_cod_amount',
        'collection_fee',
        'bank_fee',
        'other_deductions',
        'net_amount',
        'status',
        'settlement_date',
        'paid_date',
        'transfer_reference',
        'bank_name',
        'iban',
        'notes',
        'created_by',
        'paid_by',
    ];

    protected $casts = [
        'settlement_date' => 'date',
        'paid_date'       => 'date',
        'gross_cod_amount' => 'decimal:2',
        'collection_fee'   => 'decimal:2',
        'bank_fee'         => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'net_amount'       => 'decimal:2',
    ];

    public const STATUS = [
        'pending'    => 'بانتظار التنفيذ',
        'processing' => 'قيد المعالجة',
        'paid'       => 'تم الصرف',
        'cancelled'  => 'ملغاة',
    ];

    public const STATUS_COLORS = [
        'pending'    => 'warning',
        'processing' => 'info',
        'paid'       => 'success',
        'cancelled'  => 'danger',
    ];

    // ---- Relations ----

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orders()
    {
        return $this->hasMany(CodSettlementOrder::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    // ---- Helpers ----

    public static function generateNumber(): string
    {
        $year  = now()->format('Y');
        $last  = static::whereYear('created_at', $year)->max('id') ?? 0;
        $seq   = str_pad($last + 1, 4, '0', STR_PAD_LEFT);
        return "SET-{$year}-{$seq}";
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'secondary';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
