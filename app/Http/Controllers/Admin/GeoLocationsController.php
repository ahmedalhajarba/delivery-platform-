<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\Neighborhood;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class GeoLocationsController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('geo_location_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        // ===== جلب جميع البيانات الجغرافية =====
        $countries = Country::with(['regions', 'governorates', 'cities'])->get();
        $regions = Region::with(['country', 'governorates'])->get();
        $governorates = Governorate::with(['region', 'country', 'governorateCities'])->get();
        $cities = City::with(['governorate', 'country', 'cityNeighborhoods'])->get();
        $neighborhoods = Neighborhood::with(['city.governorate.region.country'])->get();

        // ===== إحصائيات =====
        $stats = [
            'countries' => $countries->count(),
            'regions' => $regions->count(),
            'governorates' => $governorates->count(),
            'cities' => $cities->count(),
            'neighborhoods' => $neighborhoods->count(),
        ];

        return view('admin.geo-locations.index', compact(
            'countries',
            'regions',
            'governorates',
            'cities',
            'neighborhoods',
            'stats'
        ));
    }

    // ===== دوال إضافية =====

    public function getRegionsByCountry($countryId)
    {
        $regions = Region::where('country_id', $countryId)
            ->orderBy('title_ar')
            ->get(['id', 'title_ar', 'title_en']);
        
        return response()->json($regions);
    }

    public function getGovernoratesByRegion($regionId)
    {
        $governorates = Governorate::where('region_id', $regionId)
            ->orderBy('title_ar')
            ->get(['id', 'title_ar', 'title_en']);
        
        return response()->json($governorates);
    }

    public function getCitiesByGovernorate($governorateId)
    {
        $cities = City::where('governorate_id', $governorateId)
            ->orderBy('title_ar')
            ->get(['id', 'title_ar', 'title_en']);
        
        return response()->json($cities);
    }

    public function getNeighborhoodsByCity($cityId)
    {
        $neighborhoods = Neighborhood::where('city_id', $cityId)
            ->orderBy('title_ar')
            ->get(['id', 'title_ar', 'title_en']);
        
        return response()->json($neighborhoods);
    }

    public function storeNeighborhood(Request $request)
    {
        abort_if(Gate::denies('geo_location_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $validated = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'region_id' => ['required', 'integer', 'exists:regions,id'],
            'governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'title_ar' => ['required', 'string', 'max:255'],
            'title_en' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'in:0,1,2'],
        ]);

        // التحقق الهرمي لضمان اتساق البيانات
        $regionBelongsToCountry = Region::where('id', $validated['region_id'])
            ->where('country_id', $validated['country_id'])
            ->exists();

        if (!$regionBelongsToCountry) {
            return back()->withInput()->withErrors([
                'region_id' => 'المنطقة المختارة لا تتبع الدولة المحددة.',
            ]);
        }

        $governorateBelongsToRegion = Governorate::where('id', $validated['governorate_id'])
            ->where('region_id', $validated['region_id'])
            ->where('country_id', $validated['country_id'])
            ->exists();

        if (!$governorateBelongsToRegion) {
            return back()->withInput()->withErrors([
                'governorate_id' => 'المحافظة المختارة لا تتبع المنطقة/الدولة المحددة.',
            ]);
        }

        $cityBelongsToGovernorate = City::where('id', $validated['city_id'])
            ->where('governorate_id', $validated['governorate_id'])
            ->where('country_id', $validated['country_id'])
            ->exists();

        if (!$cityBelongsToGovernorate) {
            return back()->withInput()->withErrors([
                'city_id' => 'المدينة المختارة لا تتبع المحافظة/الدولة المحددة.',
            ]);
        }

        $slug = trim((string) ($validated['slug'] ?? ''));
        if ($slug === '') {
            $base = Str::slug($validated['title_en']);
            if ($base === '') {
                $base = Str::slug($validated['title_ar']);
            }
            if ($base === '') {
                $base = 'neighborhood';
            }

            $slug = $base;
            $suffix = 1;
            while (Neighborhood::where('slug', $slug)->exists()) {
                $slug = $base . '-' . $suffix;
                $suffix++;
            }
        }

        Neighborhood::create([
            'city_id' => $validated['city_id'],
            'title_ar' => $validated['title_ar'],
            'title_en' => $validated['title_en'],
            'slug' => $slug,
            'type' => (string) ($validated['type'] ?? '2'),
        ]);

        return redirect()->route('admin.geo-locations.index')->with('success', 'تمت إضافة الحي بنجاح ضمن التسلسل الهرمي.');
    }
}