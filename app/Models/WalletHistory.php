<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WalletHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'wallet_histories';

    protected $fillable = [
        'user_id',
        'wallet_id',
        'amount',
        'type',
        'status',
        'description',
        'reference_id',
        'reference_type',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // العلاقات
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wallet()
    {
        return $this->belongsTo(WalletTitle::class, 'wallet_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // دالة مساعدة للحصول على نوع العملية
    public function getTypeLabelAttribute()
    {
        $types = [
            'deposit' => 'إيداع',
            'withdrawal' => 'سحب',
            'payment' => 'دفع',
            'refund' => 'استرجاع',
            'bonus' => 'مكافأة',
            'commission' => 'عمولة',
        ];
        return $types[$this->type] ?? $this->type;
    }

    // دالة مساعدة للحصول على حالة العملية
    public function getStatusLabelAttribute()
    {
        $statuses = [
            'pending' => 'قيد الانتظار',
            'approved' => 'موافق عليه',
            'rejected' => 'مرفوض',
            'completed' => 'مكتمل',
            'cancelled' => 'ملغي',
        ];
        return $statuses[$this->status] ?? $this->status;
    }
}