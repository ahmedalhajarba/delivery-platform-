<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    public $table = 'invoice_items';

    protected $fillable = [
        'invoice_id',
        'order_id',
        'source_type',
        'source_id',
        'description',
        'quantity',
        'unit_price',
        'discount',
        'total',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'discount'   => 'decimal:2',
        'total'      => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        // احتساب الإجمالي تلقائياً
        static::saving(function ($item) {
            $item->total = ($item->unit_price * $item->quantity) - $item->discount;
        });
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
