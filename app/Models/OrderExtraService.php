<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderExtraService extends Model
{
    public $table = 'order_extra_services';

    public const SERVICE_TYPES = [
        'overweight'        => 'رسوم وزن زائد',
        'packaging'         => 'رسوم تغليف',
        'storage_normal'    => 'تخزين عادي',
        'storage_cold'      => 'تخزين مبرد',
        'delivery_attempt'  => 'محاولة توصيل إضافية',
        'other'             => 'أخرى',
    ];

    public const SERVICE_ICONS = [
        'overweight'        => 'bi-boxes',
        'packaging'         => 'bi-gift',
        'storage_normal'    => 'bi-archive',
        'storage_cold'      => 'bi-snow',
        'delivery_attempt'  => 'bi-truck',
        'other'             => 'bi-plus-circle',
    ];

    protected $fillable = [
        'order_id',
        'service_type',
        'description',
        'quantity',
        'unit_price',
        'total_amount',
        'created_by',
    ];

    protected $casts = [
        'quantity'     => 'decimal:2',
        'unit_price'   => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getServiceLabelAttribute(): string
    {
        return self::SERVICE_TYPES[$this->service_type] ?? $this->service_type;
    }
}
