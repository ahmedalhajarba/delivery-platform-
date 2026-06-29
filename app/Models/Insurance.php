<?php

namespace App\Models;

use \DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Insurance extends Model
{
    use SoftDeletes;
    use HasFactory;

    const STATUS = [
        'issued' => 'صادرة',
        'active' => 'سارية',
        'expired' => 'منتهية',
        'cancelled' => 'ملغية',
        'claimed' => 'تمت المطالبة',
    ];

    public $table = 'insurances';

    protected $dates = [
        'issued_at',
        'start_date',
        'end_date',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'policy_number',
        'insurance_rate',
        'total',
        'note',
        'order_id',
        'invoice_id',
        'receipt_id',
        'original_receipt_number',
        'status',
        'issued_at',
        'start_date',
        'end_date',
        'terms_and_conditions',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'issued_at' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $insurance) {
            if (empty($insurance->policy_number)) {
                $insurance->policy_number = self::generatePolicyNumber();
            }

            if (empty($insurance->status)) {
                $insurance->status = 'issued';
            }

            if (empty($insurance->issued_at)) {
                $insurance->issued_at = now()->toDateString();
            }

            if (empty($insurance->start_date)) {
                $insurance->start_date = now()->toDateString();
            }
        });
    }

    public static function generatePolicyNumber(): string
    {
        $prefix = 'POL-' . now()->format('Ym') . '-';
        $last = self::withTrashed()
            ->where('policy_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->first();

        $next = 1;
        if ($last && !empty($last->policy_number)) {
            $tail = Str::after($last->policy_number, $prefix);
            $next = max(1, ((int) $tail) + 1);
        }

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
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

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function getDurationDaysAttribute(): ?int
    {
        if (!$this->start_date || !$this->end_date) {
            return null;
        }

        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
