<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntlShippingRate extends Model
{
    public $table = 'intl_shipping_rates';

    protected $fillable = [
        'origin_country_id',
        'destination_country_id',
        'from_weight',
        'to_weight',
        'base_price',
        'price_per_kg',
        'cold_surcharge',
        'frozen_surcharge',
        'dry_surcharge',
        'carrier_label',
        'transit_days_min',
        'transit_days_max',
        'enabled',
    ];

    protected $casts = [
        'from_weight'     => 'decimal:2',
        'to_weight'       => 'decimal:2',
        'base_price'      => 'decimal:2',
        'price_per_kg'    => 'decimal:2',
        'cold_surcharge'  => 'decimal:2',
        'frozen_surcharge'=> 'decimal:2',
        'dry_surcharge'   => 'decimal:2',
        'enabled'         => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────

    public function originCountry()
    {
        return $this->belongsTo(Country::class, 'origin_country_id');
    }

    public function destinationCountry()
    {
        return $this->belongsTo(Country::class, 'destination_country_id');
    }

    // ── Scope: enabled rates for a destination ─────────────────

    public static function ratesFor(int $destinationCountryId, ?int $originCountryId = null)
    {
        return static::where('destination_country_id', $destinationCountryId)
            ->where('enabled', true)
            ->where(function ($q) use ($originCountryId) {
                $q->whereNull('origin_country_id');
                if ($originCountryId) {
                    $q->orWhere('origin_country_id', $originCountryId);
                }
            })
            ->orderByRaw('CASE WHEN origin_country_id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('from_weight')
            ->get();
    }

    // ── Calculate price for given weight and shipping type ──────

    /**
     * Find the best rate and calculate total base price.
     * Returns float or null if no rate found.
     */
    public static function calcPrice(
        int $destinationCountryId,
        float $chargeableWeight,
        string $shippingType = 'standard',
        ?int $originCountryId = null
    ): ?float {
        $rates = static::ratesFor($destinationCountryId, $originCountryId);

        // Find matching bracket
        $match = null;
        foreach ($rates as $rate) {
            if ($chargeableWeight >= (float) $rate->from_weight &&
                ($rate->to_weight === null || $chargeableWeight < (float) $rate->to_weight)) {
                $match = $rate;
                break;
            }
        }

        if (!$match) return null;

        // Base + per-kg
        $excess = max(0, $chargeableWeight - (float) $match->from_weight);
        $price  = (float) $match->base_price + ($excess * (float) $match->price_per_kg);

        // Shipping type surcharge
        if ($shippingType === 'cold')   $price += (float) $match->cold_surcharge;
        if ($shippingType === 'frozen') $price += (float) $match->frozen_surcharge;
        if ($shippingType === 'dry')    $price += (float) $match->dry_surcharge;

        return round($price, 2);
    }
}
