<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourierSettlementItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'courier_settlement_id',
        'courier_trip_financial_id',
        'net_amount',
    ];

    protected $casts = [
        'net_amount' => 'decimal:2',
    ];

    public function settlement()
    {
        return $this->belongsTo(CourierSettlement::class, 'courier_settlement_id');
    }

    public function tripFinancial()
    {
        return $this->belongsTo(CourierTripFinancial::class, 'courier_trip_financial_id');
    }
}
