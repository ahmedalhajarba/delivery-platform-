<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\IntlShippingRate;
use Illuminate\Http\Request;

class IntlShippingRatesController extends Controller
{
    // ── Main settings page ──────────────────────────────────────

    public function index(Request $request)
    {
        $countries        = Country::orderBy('name')->get();
        $selectedDest     = $request->get('destination_country_id');
        $selectedOrigin   = $request->get('origin_country_id');

        $ratesQuery = IntlShippingRate::with(['originCountry', 'destinationCountry'])
            ->orderBy('destination_country_id')
            ->orderByRaw('CASE WHEN origin_country_id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('from_weight');

        if ($selectedDest)   $ratesQuery->where('destination_country_id', $selectedDest);
        if ($selectedOrigin) $ratesQuery->where('origin_country_id', $selectedOrigin);

        $rates = $ratesQuery->get();

        // Group by destination for display
        $grouped = $rates->groupBy('destination_country_id');

        return view('admin.intlShippingRates.index', compact(
            'countries', 'rates', 'grouped', 'selectedDest', 'selectedOrigin'
        ));
    }

    // ── Save / update a single rate row ────────────────────────

    public function upsert(Request $request)
    {
        $data = $request->validate([
            'id'                       => ['nullable', 'integer', 'exists:intl_shipping_rates,id'],
            'origin_country_id'        => ['nullable', 'exists:countries,id'],
            'destination_country_id'   => ['required', 'exists:countries,id'],
            'from_weight'              => ['required', 'numeric', 'min:0'],
            'to_weight'                => ['nullable', 'numeric', 'min:0'],
            'base_price'               => ['required', 'numeric', 'min:0'],
            'price_per_kg'             => ['nullable', 'numeric', 'min:0'],
            'cold_surcharge'           => ['nullable', 'numeric', 'min:0'],
            'frozen_surcharge'         => ['nullable', 'numeric', 'min:0'],
            'dry_surcharge'            => ['nullable', 'numeric', 'min:0'],
            'carrier_label'            => ['nullable', 'string', 'max:191'],
            'transit_days_min'         => ['nullable', 'integer', 'min:0'],
            'transit_days_max'         => ['nullable', 'integer', 'min:0'],
            'enabled'                  => ['nullable', 'boolean'],
        ]);

        $data['enabled']         = $request->boolean('enabled', true);
        $data['price_per_kg']    = $data['price_per_kg']    ?? 0;
        $data['cold_surcharge']  = $data['cold_surcharge']  ?? 0;
        $data['frozen_surcharge']= $data['frozen_surcharge']?? 0;
        $data['dry_surcharge']   = $data['dry_surcharge']   ?? 0;

        if (!empty($data['id'])) {
            $rate = IntlShippingRate::findOrFail($data['id']);
            $rate->update($data);
        } else {
            unset($data['id']);
            $rate = IntlShippingRate::create($data);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'row' => $rate->load(['originCountry', 'destinationCountry'])]);
        }

        return back()->with('message', 'تم حفظ السعر الدولي بنجاح');
    }

    // ── Delete ─────────────────────────────────────────────────

    public function destroy(IntlShippingRate $intlShippingRate)
    {
        $intlShippingRate->delete();
        return back()->with('message', 'تم حذف السعر');
    }

    // ── AJAX: calculate price for order form ──────────────────

    public function calcPrice(Request $request)
    {
        $destId  = (int) $request->input('destination_country_id');
        $weight  = (float) $request->input('chargeable_weight', 0);
        $type    = $request->input('shipping_type', 'standard');
        $origId  = $request->input('origin_country_id') ? (int) $request->input('origin_country_id') : null;

        $price = IntlShippingRate::calcPrice($destId, $weight, $type, $origId);

        // Also find the matching rate for transit days info
        $rates = IntlShippingRate::ratesFor($destId, $origId);
        $matchedRate = null;
        foreach ($rates as $r) {
            if ($weight >= (float) $r->from_weight &&
                ($r->to_weight === null || $weight < (float) $r->to_weight)) {
                $matchedRate = $r;
                break;
            }
        }

        return response()->json([
            'price'         => $price,
            'transit_min'   => $matchedRate?->transit_days_min,
            'transit_max'   => $matchedRate?->transit_days_max,
            'carrier_label' => $matchedRate?->carrier_label,
        ]);
    }

    // ── AJAX: get all rates for a destination country ─────────

    public function ratesForCountry(Country $country)
    {
        $rates = IntlShippingRate::where('destination_country_id', $country->id)
            ->where('enabled', true)
            ->orderBy('from_weight')
            ->get(['id', 'from_weight', 'to_weight', 'base_price', 'price_per_kg',
                   'cold_surcharge', 'frozen_surcharge', 'dry_surcharge',
                   'carrier_label', 'transit_days_min', 'transit_days_max']);

        return response()->json($rates);
    }
}
