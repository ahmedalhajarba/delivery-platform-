<?php

namespace App\Models;

use DateTimeInterface;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashBox extends Model
{
    use SoftDeletes, Auditable, HasFactory;

    public $table = 'cash_boxes';

    const TYPE = [
        'main'    => 'صندوق رئيسي',
        'branch'  => 'صندوق فرع',
        'courier' => 'صندوق مندوب',
    ];

    const CHANNEL = [
        'cash_box' => 'صندوق نقدي',
        'bank_account' => 'حساب بنكي',
        'payment_gateway' => 'بوابة دفع',
        'employee_custody' => 'عهدة موظف',
    ];

    protected $fillable = [
        'name',
        'name_ar',
        'name_en',
        'account_name',
        'account_number',
        'iban',
        'gateway_name',
        'balance',
        'type',
        'channel',
        'branch_id',
        'courier_id',
        'manager_user_id',
        'is_active',
        'is_default',
        'currency',
        'description',
        'notes',
        'created_by',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'balance'   => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    // ========== Relationships ==========

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function courier()
    {
        return $this->belongsTo(BranchEmployee::class, 'courier_id');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function receipts()
    {
        return $this->hasMany(Receipt::class, 'cash_box_id');
    }

    public function transactions()
    {
        return $this->hasMany(CashBoxTransaction::class, 'cash_box_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ========== Methods ==========

    /**
     * إيداع مبلغ في الصندوق وتسجيل الحركة
     */
    public function deposit(float $amount, string $description = '', ?int $receiptId = null, ?int $performedBy = null): CashBoxTransaction
    {
        $before = $this->balance;
        $this->increment('balance', $amount);
        
        return $this->transactions()->create([
            'transaction_type' => 'credit',
            'amount'           => $amount,
            'balance_before'   => $before,
            'balance_after'    => $this->fresh()->balance,
            'description'      => $description,
            'receipt_id'       => $receiptId,
            'performed_by'     => $performedBy,
        ]);
    }

    /**
     * سحب مبلغ من الصندوق
     */
    public function withdraw(float $amount, string $description = '', ?int $performedBy = null): CashBoxTransaction
    {
        $before = $this->balance;
        $this->decrement('balance', $amount);
        
        return $this->transactions()->create([
            'transaction_type' => 'debit',
            'amount'           => $amount,
            'balance_before'   => $before,
            'balance_after'    => $this->fresh()->balance,
            'description'      => $description,
            'performed_by'     => $performedBy,
        ]);
    }

    /**
     * الحصول على اسم الصندوق حسب اللغة
     */
    public function getNameAttribute(): string
    {
        if (app()->getLocale() === 'en') {
            return $this->name_en ?? $this->name_ar ?? $this->name ?? 'صندوق';
        }
        return $this->name_ar ?? $this->name ?? 'صندوق';
    }

    /**
     * الحصول على اسم الصندوق بالعربية
     */
    public function getNameArAttribute()
    {
        return $this->attributes['name_ar'] ?? $this->attributes['name'] ?? 'صندوق';
    }

    /**
     * الحصول على اسم الصندوق بالإنجليزية
     */
    public function getNameEnAttribute()
    {
        return $this->attributes['name_en'] ?? $this->attributes['name'] ?? 'Cash Box';
    }

    /**
     * الحصول على تسمية القناة
     */
    public function getChannelLabelAttribute(): string
    {
        return self::CHANNEL[$this->channel] ?? $this->channel ?? 'غير محدد';
    }

    /**
     * الحصول على تسمية النوع
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPE[$this->type] ?? $this->type ?? 'غير محدد';
    }

    /**
     * التحقق من أن الصندوق نشط
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * التحقق من أن الصندوق افتراضي
     */
    public function isDefault(): bool
    {
        return (bool) $this->is_default;
    }

    /**
     * نطاق البحث عن الصناديق النشطة
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * نطاق البحث عن الصناديق الافتراضية
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * نطاق البحث حسب النوع
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * نطاق البحث حسب القناة
     */
    public function scopeByChannel($query, $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * نطاق البحث حسب المدير
     */
    public function scopeByManager($query, $managerId)
    {
        return $query->where('manager_user_id', $managerId);
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}