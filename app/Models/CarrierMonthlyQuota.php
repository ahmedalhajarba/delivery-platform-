<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarrierMonthlyQuota extends Model
{
    protected $table = 'carrier_monthly_quotas';
    protected $fillable = [
        'carrier_id',
        'year',
        'month',
        'total_waybills_cap',
        'subscription_pool_cap',
        'is_active',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'total_waybills_cap' => 'integer',
        'subscription_pool_cap' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the carrier company associated with this quota.
     */
    public function carrierCompany(): BelongsTo
    {
        return $this->belongsTo(CarrierCompany::class, 'carrier_id');
    }

    /**
     * Get the monthly consumption record for this quota.
     */
    public function consumption(): BelongsTo
    {
        return $this->belongsTo(CarrierMonthlyConsumption::class, ['carrier_id', 'year', 'month'], ['carrier_id', 'year', 'month']);
    }

    /**
     * Get the individual pool remaining (total cap - subscription pool cap).
     */
    public function getIndividualPoolCapAttribute(): int
    {
        return $this->total_waybills_cap - $this->subscription_pool_cap;
    }

    /**
     * Check if subscription pool is exhausted.
     */
    public function isSubscriptionPoolExhausted(): bool
    {
        $consumption = CarrierMonthlyConsumption::where('carrier_id', $this->carrier_id)
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->first();

        if (!$consumption) {
            return false; // No consumption yet
        }

        return $consumption->subscription_used >= $this->subscription_pool_cap;
    }

    /**
     * Check if total quota is exhausted.
     */
    public function isTotalQuotaExhausted(): bool
    {
        $consumption = CarrierMonthlyConsumption::where('carrier_id', $this->carrier_id)
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->first();

        if (!$consumption) {
            return false; // No consumption yet
        }

        return ($consumption->subscription_used + $consumption->individual_used) >= $this->total_waybills_cap;
    }

    /**
     * Get remaining subscription pool.
     */
    public function getSubscriptionPoolRemainingAttribute(): int
    {
        $consumption = CarrierMonthlyConsumption::where('carrier_id', $this->carrier_id)
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->first();

        if (!$consumption) {
            return $this->subscription_pool_cap;
        }

        return max(0, $this->subscription_pool_cap - $consumption->subscription_used);
    }

    /**
     * Get remaining total quota.
     */
    public function getTotalQuotaRemainingAttribute(): int
    {
        $consumption = CarrierMonthlyConsumption::where('carrier_id', $this->carrier_id)
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->first();

        if (!$consumption) {
            return $this->total_waybills_cap;
        }

        return max(0, $this->total_waybills_cap - ($consumption->subscription_used + $consumption->individual_used));
    }

    /**
     * Get percentage of subscription pool used.
     */
    public function getSubscriptionPoolPercentageAttribute(): float
    {
        if ($this->subscription_pool_cap == 0) {
            return 0;
        }

        $consumption = CarrierMonthlyConsumption::where('carrier_id', $this->carrier_id)
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->first();

        if (!$consumption) {
            return 0;
        }

        return ($consumption->subscription_used / $this->subscription_pool_cap) * 100;
    }
}
