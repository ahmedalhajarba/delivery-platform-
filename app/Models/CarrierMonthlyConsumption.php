<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarrierMonthlyConsumption extends Model
{
    protected $table = 'carrier_monthly_consumptions';
    protected $fillable = [
        'carrier_id',
        'year',
        'month',
        'subscription_used',
        'individual_used',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'subscription_used' => 'integer',
        'individual_used' => 'integer',
    ];

    /**
     * Get the carrier company associated with this consumption.
     */
    public function carrierCompany(): BelongsTo
    {
        return $this->belongsTo(CarrierCompany::class, 'carrier_id');
    }

    /**
     * Get the quota configuration for this consumption period.
     */
    public function quota(): BelongsTo
    {
        return $this->belongsTo(CarrierMonthlyQuota::class, ['carrier_id', 'year', 'month'], ['carrier_id', 'year', 'month']);
    }

    /**
     * Increment subscription consumption by 1.
     */
    public function incrementSubscription(): bool
    {
        return $this->increment('subscription_used');
    }

    /**
     * Increment individual consumption by 1.
     */
    public function incrementIndividual(): bool
    {
        return $this->increment('individual_used');
    }

    /**
     * Decrement subscription consumption (for refunds/cancellations).
     */
    public function decrementSubscription(): bool
    {
        return $this->decrement('subscription_used', 1);
    }

    /**
     * Decrement individual consumption (for refunds/cancellations).
     */
    public function decrementIndividual(): bool
    {
        return $this->decrement('individual_used', 1);
    }

    /**
     * Get total consumption.
     */
    public function getTotalUsedAttribute(): int
    {
        return $this->subscription_used + $this->individual_used;
    }
}
