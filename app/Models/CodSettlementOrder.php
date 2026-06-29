<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodSettlementOrder extends Model
{
    public $table = 'cod_settlement_orders';

    protected $fillable = [
        'cod_settlement_id',
        'order_id',
        'cod_amount',
        'collection_fee',
        'net_amount',
    ];

    protected $casts = [
        'cod_amount'     => 'decimal:2',
        'collection_fee' => 'decimal:2',
        'net_amount'     => 'decimal:2',
    ];

    public function settlement()
    {
        return $this->belongsTo(CodSettlement::class, 'cod_settlement_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
