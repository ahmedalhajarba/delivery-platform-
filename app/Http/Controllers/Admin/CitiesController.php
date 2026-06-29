<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyCityRequest;
use App\Http\Requests\StoreCityRequest;
use App\Http\Requests\UpdateCityRequest;
use App\Models\Branch;
use App\Models\City;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\Region;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CitiesController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('city_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        // إزالة region من الـ with لأن العلاقة غير موجودة مباشرة
        $cities = City::with(['governorate', 'country'])->get();

        return view('admin.cities.index', compact('cities'));
    }

    public function create()
    {
        abort_if(Gate::denies('city_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        // ===== جلب البيانات المطلوبة للـ View =====
        $countries = Country::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');
        $governorates = Governorate::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');
        $regions = Region::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');
        $branches = Branch::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');
        $users = User::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.cities.create', compact('countries', 'governorates', 'regions', 'branches', 'users'));
    }

    public function store(StoreCityRequest $request)
    {
        $city = City::create($request->all());

        return redirect()->route('admin.cities.index');
    }

    public function edit(City $city)
    {
        abort_if(Gate::denies('city_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        // ===== جلب البيانات المطلوبة للـ View =====
        $countries = Country::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');
        $governorates = Governorate::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');
        $regions = Region::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');
        $branches = Branch::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');
        $users = User::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $city->load('governorate', 'country');

        return view('admin.cities.edit', compact('city', 'countries', 'governorates', 'regions', 'branches', 'users'));
    }

    public function update(UpdateCityRequest $request, City $city)
    {
        $city->update($request->all());

        return redirect()->route('admin.cities.index');
    }

    public function show(City $city)
    {
        abort_if(Gate::denies('city_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $city->load('governorate', 'country', 'cityUsers', 'cityCompanies', 'cityBranchEmployees', 'cityNeighborhoods');

        return view('admin.cities.show', compact('city'));
    }

    public function destroy(City $city)
    {
        abort_if(Gate::denies('city_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $city->delete();

        return back();
    }

    public function massDestroy(MassDestroyCityRequest $request)
    {
        City::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    // ===== دوال AJAX =====

    public function getGovernorates(Request $request)
    {
        $governorates = Governorate::where('region_id', $request->region_id)
            ->pluck('title_ar', 'id')
            ->prepend(trans('global.pleaseSelect'), '');
        
        return response()->json($governorates);
    }

    public function getRegions(Request $request)
    {
        $regions = Region::where('country_id', $request->country_id)
            ->pluck('title_ar', 'id')
            ->prepend(trans('global.pleaseSelect'), '');
        
        return response()->json($regions);
    }

    public function cityCountry(City $city)
    {
        $city->loadMissing('country');

        return response()->json([
            'city_id' => $city->id,
            'city_title' => $city->title_ar,
            'country_id' => $city->country_id,
            'country_title' => optional($city->country)->name ?? optional($city->country)->title_ar,
        ]);
    }
}