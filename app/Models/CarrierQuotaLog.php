<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarrierQuotaLog extends Model
{
    protected $table = 'carrier_quota_logs';
    protected $fillable = [
        'carrier_id',
        'order_id',
        'year',
        'month',
        'action',
        'order_type',
        'quantity_change',
        'reason',
        'user_id',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'quantity_change' => 'integer',
    ];

    /**
     * Get the carrier company associated with this log.
     */
    public function carrierCompany(): BelongsTo
    {
        return $this->belongsTo(CarrierCompany::class, 'carrier_id');
    }

    /**
     * Get the order associated with this log (if any).
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who made this log entry (for manual adjustments).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
