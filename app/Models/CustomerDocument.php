<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerDocument extends Model
{
    use SoftDeletes;

    public $table = 'customer_documents';

    const DOC_TYPES = [
        'national_id'          => 'هوية وطنية',
        'commercial_register'  => 'سجل تجاري',
        'tax_cert'             => 'شهادة ضريبية',
        'signed_contract'      => 'عقد موقع',
        'bank_statement'       => 'كشف حساب بنكي',
        'authorization'        => 'توكيل رسمي',
        'id_card'              => 'بطاقة هوية',
        'passport'             => 'جواز سفر',
        'driver_license'       => 'رخصة قيادة',
        'tax_card'             => 'بطاقة ضريبية',
        'other'                => 'أخرى',
    ];

    const STATUS_COLORS = [
        'pending'  => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
        'expired'  => 'dark',
        'verified' => 'success',
    ];

    const STATUS_LABELS = [
        'pending'  => 'قيد المراجعة',
        'approved' => 'مقبول',
        'rejected' => 'مرفوض',
        'expired'  => 'منتهي الصلاحية',
        'verified' => 'تم التحقق',
    ];

    protected $fillable = [
        'user_id',
        'customer_profile_id',
        'doc_type',
        'document_type',
        'document_name',
        'title',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'file_type',
        'expiry_date',
        'status',
        'description',
        'notes',
        'reviewed_by',
        'verified_by',
        'reviewed_at',
        'verified_at',
        'uploaded_by',
    ];

    protected $casts = [
        'expiry_date'  => 'date',
        'reviewed_at'  => 'datetime',
        'verified_at'  => 'datetime',
        'file_size'    => 'integer',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
        'deleted_at'   => 'datetime',
    ];

    // العلاقات
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customerProfile()
    {
        return $this->belongsTo(CustomerProfile::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // الحصول على رابط الملف
    public function getUrlAttribute(): string
    {
        if ($this->file_path) {
            return asset('storage/' . $this->file_path);
        }
        return '#';
    }

    /**
     * الحصول على تسمية نوع المستند
     * تم إضافة التحقق من وجود القيمة
     */
    public function getDocTypeLabelAttribute(): string
    {
        // التحقق من doc_type أولاً
        if ($this->doc_type) {
            return self::DOC_TYPES[$this->doc_type] ?? $this->doc_type;
        }
        // إذا كان doc_type فارغ، تحقق من document_type
        if ($this->document_type) {
            return self::DOC_TYPES[$this->document_type] ?? $this->document_type;
        }
        return 'غير محدد';
    }

    /**
     * الحصول على تسمية حالة المستند
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ($this->status ?? 'غير محدد');
    }

    /**
     * الحصول على لون حالة المستند
     */
    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'secondary';
    }

    /**
     * التحقق من انتهاء صلاحية المستند قريباً
     */
    public function isExpiringSoon(): bool
    {
        return $this->expiry_date && $this->expiry_date->diffInDays(now()) <= 30 && $this->expiry_date->isFuture();
    }

    /**
     * التحقق من أن المستند منتهي الصلاحية
     */
    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    /**
     * التحقق من أن المستند مقبول
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved' || $this->status === 'verified';
    }

    /**
     * التحقق من أن المستند قيد الانتظار
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}