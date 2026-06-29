<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingPrice extends Model
{
    public $table = 'shipping_prices';

    protected $fillable = [
        'country_id',
        'region_id',
        'governorate_id',
        'city_id',
        'base_price',
        'label',
        'enabled',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'enabled'    => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function governorate()
    {
        return $this->belongsTo(Governorate::class, 'governorate_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    // ── Lookup: most-specific price matching the given geo IDs ──

    /**
     * Find the best matching shipping price for the given address.
     * Precedence: city > governorate > region > country
     *
     * @return float|null
     */
    public static function lookupPrice(
        ?int $countryId,
        ?int $regionId,
        ?int $governorateId,
        ?int $cityId
    ): ?float {
        // Try most specific first
        $candidates = [
            ['country_id' => $countryId, 'region_id' => $regionId, 'governorate_id' => $governorateId, 'city_id' => $cityId],
            ['country_id' => $countryId, 'region_id' => $regionId, 'governorate_id' => $governorateId, 'city_id' => null],
            ['country_id' => $countryId, 'region_id' => $regionId, 'governorate_id' => null,           'city_id' => null],
            ['country_id' => $countryId, 'region_id' => null,       'governorate_id' => null,           'city_id' => null],
        ];

        foreach ($candidates as $cond) {
            $row = static::where($cond)->where('enabled', true)->first();
            if ($row) {
                return (float) $row->base_price;
            }
        }

        return null;
    }
}
