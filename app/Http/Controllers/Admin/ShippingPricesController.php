<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\Region;
use App\Models\ShippingPrice;
use Illuminate\Http\Request;

class ShippingPricesController extends Controller
{
    // ── Main settings page ─────────────────────────────────────

    public function index(Request $request)
    {
        $countries    = Country::orderBy('name')->get();
        $selectedCountry = $request->get('country_id');
        $selectedRegion  = $request->get('region_id');

        $regions      = $selectedCountry
            ? Region::where('country_id', $selectedCountry)->orderBy('title_ar')->get()
            : collect();

        $governorates = $selectedRegion
            ? Governorate::where('region_id', $selectedRegion)->orderBy('title_ar')->get()
            : collect();

        // Build the price rows already saved for this scope
        $pricesQuery = ShippingPrice::query();
        if ($selectedCountry)  $pricesQuery->where('country_id', $selectedCountry);
        if ($selectedRegion)   $pricesQuery->where('region_id',  $selectedRegion);
        $prices = $pricesQuery
            ->with(['country', 'region', 'governorate', 'city'])
            ->orderBy('country_id')->orderBy('region_id')
            ->orderBy('governorate_id')->orderBy('city_id')
            ->get();

        return view('admin.shippingPrices.index', compact(
            'countries', 'regions', 'governorates',
            'selectedCountry', 'selectedRegion', 'prices'
        ));
    }

    // ── Save / update a single price row ───────────────────────

    public function upsert(Request $request)
    {
        $data = $request->validate([
            'country_id'      => ['nullable', 'exists:countries,id'],
            'region_id'       => ['nullable', 'exists:regions,id'],
            'governorate_id'  => ['nullable', 'exists:governorates,id'],
            'city_id'         => ['nullable', 'exists:cities,id'],
            'base_price'      => ['required', 'numeric', 'min:0'],
            'label'           => ['nullable', 'string', 'max:255'],
            'enabled'         => ['nullable', 'boolean'],
        ]);

        $data['enabled'] = $request->boolean('enabled', true);

        $row = ShippingPrice::updateOrCreate(
            [
                'country_id'     => $data['country_id']     ?? null,
                'region_id'      => $data['region_id']      ?? null,
                'governorate_id' => $data['governorate_id'] ?? null,
                'city_id'        => $data['city_id']        ?? null,
            ],
            [
                'base_price' => $data['base_price'],
                'label'      => $data['label'] ?? null,
                'enabled'    => $data['enabled'],
            ]
        );

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'row' => $row]);
        }

        return back()->with('message', 'تم حفظ السعر بنجاح');
    }

    // ── Batch save (from the main form table) ──────────────────

    public function batchUpdate(Request $request)
    {
        $request->validate([
            'prices'               => ['array'],
            'prices.*.country_id'     => ['nullable', 'exists:countries,id'],
            'prices.*.region_id'      => ['nullable', 'exists:regions,id'],
            'prices.*.governorate_id' => ['nullable', 'exists:governorates,id'],
            'prices.*.city_id'        => ['nullable', 'exists:cities,id'],
            'prices.*.base_price'     => ['required', 'numeric', 'min:0'],
            'prices.*.label'          => ['nullable', 'string', 'max:255'],
            'prices.*.enabled'        => ['nullable'],
        ]);

        foreach ($request->input('prices', []) as $item) {
            ShippingPrice::updateOrCreate(
                [
                    'country_id'     => $item['country_id']     ?? null,
                    'region_id'      => $item['region_id']      ?? null,
                    'governorate_id' => $item['governorate_id'] ?? null,
                    'city_id'        => $item['city_id']        ?? null,
                ],
                [
                    'base_price' => $item['base_price'],
                    'label'      => $item['label'] ?? null,
                    'enabled'    => isset($item['enabled']) && $item['enabled'] ? true : false,
                ]
            );
        }

        return back()->with('message', 'تم حفظ جميع الأسعار بنجاح');
    }

    // ── Delete a row ───────────────────────────────────────────

    public function destroy(ShippingPrice $shippingPrice)
    {
        $shippingPrice->delete();
        return back()->with('message', 'تم حذف السعر');
    }

    // ── AJAX: load regions for a country ──────────────────────

    public function regions(Country $country)
    {
        return response()->json(
            Region::where('country_id', $country->id)
                ->orderBy('title_ar')
                ->get(['id', 'title_ar', 'title_en'])
        );
    }

    // ── AJAX: load governorates for a region ──────────────────

    public function governorates(Region $region)
    {
        return response()->json(
            Governorate::where('region_id', $region->id)
                ->orderBy('title_ar')
                ->get(['id', 'title_ar', 'title_en'])
        );
    }

    // ── AJAX: load cities for a governorate ───────────────────

    public function cities(Governorate $governorate)
    {
        return response()->json(
            City::where('governorate_id', $governorate->id)
                ->orderBy('title_ar')
                ->get(['id', 'title_ar', 'title_en'])
        );
    }

    // ── AJAX: lookup price for an address (used in create order) ──

    public function lookup(Request $request)
    {
        $price = ShippingPrice::lookupPrice(
            $request->input('country_id')     ? (int) $request->input('country_id')     : null,
            $request->input('region_id')      ? (int) $request->input('region_id')      : null,
            $request->input('governorate_id') ? (int) $request->input('governorate_id') : null,
            $request->input('city_id')        ? (int) $request->input('city_id')        : null
        );

        return response()->json(['price' => $price]);
    }
}
