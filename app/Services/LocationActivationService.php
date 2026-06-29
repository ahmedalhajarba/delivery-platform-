<?php

namespace App\Services;

use App\Models\City;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\Neighborhood;
use App\Models\Region;
use RuntimeException;

class LocationActivationService
{
    public function assertOrderCreationAllowed(
        ?int $senderCityId,
        ?int $senderNeighborhoodId,
        ?int $recipientCityId,
        ?int $recipientNeighborhoodId
    ): void {
        $senderChain = $this->resolveChain($senderCityId, $senderNeighborhoodId);
        $recipientChain = $this->resolveChain($recipientCityId, $recipientNeighborhoodId);

        $this->assertChainAllows($senderChain, ['is_active', 'allow_pickup'], 'المرسل');
        $this->assertChainAllows($recipientChain, ['is_active', 'allow_delivery'], 'المستلم');
    }

    public function assertSubscriptionAllowed(?int $cityId, ?int $neighborhoodId = null): void
    {
        $chain = $this->resolveChain($cityId, $neighborhoodId);
        $this->assertChainAllows($chain, ['is_active', 'allow_subscriptions'], 'الاشتراك');
    }

    public function assertExtraServicesAllowed(?int $cityId, ?int $neighborhoodId = null): void
    {
        $chain = $this->resolveChain($cityId, $neighborhoodId);
        $this->assertChainAllows($chain, ['is_active', 'allow_extra_services'], 'الخدمات الإضافية');
    }

    private function resolveChain(?int $cityId, ?int $neighborhoodId): array
    {
        $neighborhood = null;
        $city = null;

        if ($neighborhoodId) {
            $neighborhood = Neighborhood::with('city.governorate.region.country')->find($neighborhoodId);
            if ($neighborhood) {
                $city = $neighborhood->city;
            }
        }

        if (!$city && $cityId) {
            $city = City::with('governorate.region.country')->find($cityId);
        }

        $governorate = $city?->governorate;
        $region = $governorate?->region;
        $country = $region?->country;

        return [
            'country' => $country,
            'region' => $region,
            'governorate' => $governorate,
            'city' => $city,
            'neighborhood' => $neighborhood,
        ];
    }

    private function assertChainAllows(array $chain, array $flags, string $scope): void
    {
        $ordered = [
            'country' => ['label' => 'الدولة', 'model' => Country::class],
            'region' => ['label' => 'المنطقة', 'model' => Region::class],
            'governorate' => ['label' => 'المحافظة', 'model' => Governorate::class],
            'city' => ['label' => 'المدينة', 'model' => City::class],
            'neighborhood' => ['label' => 'الحي/النطاق', 'model' => Neighborhood::class],
        ];

        foreach ($ordered as $key => $meta) {
            $item = $chain[$key] ?? null;
            if (!$item) {
                continue;
            }

            foreach ($flags as $flag) {
                if ((bool) $item->{$flag} === false) {
                    throw new RuntimeException(sprintf('%s غير متاح بسبب تعطيل %s: %s', $scope, $meta['label'], $this->displayName($item)));
                }
            }
        }
    }

    private function displayName(object $model): string
    {
        if (isset($model->title_ar) && !empty($model->title_ar)) {
            return (string) $model->title_ar;
        }

        if (isset($model->name) && !empty($model->name)) {
            return (string) $model->name;
        }

        return '#'.$model->id;
    }
}
