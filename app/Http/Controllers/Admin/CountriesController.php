<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyCountryRequest;
use App\Http\Requests\StoreCountryRequest;
use App\Http\Requests\UpdateCountryRequest;
use App\Models\Branch;
use App\Models\City;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\Region;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CountriesController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('country_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $countries = Country::with(['regions', 'governorates', 'cities'])->get();

        return view('admin.countries.index', compact('countries'));
    }

    public function create()
    {
        abort_if(Gate::denies('country_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branches = Branch::query()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');
        $users = User::query()->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.countries.create', compact('branches', 'users'));
    }

    public function store(StoreCountryRequest $request)
    {
        $country = Country::create($request->all());

        return redirect()->route('admin.countries.index');
    }

    public function edit(Country $country)
    {
        abort_if(Gate::denies('country_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branches = Branch::query()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');
        $users = User::query()->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $country->load('branch', 'responsibleUser');

        return view('admin.countries.edit', compact('country', 'branches', 'users'));
    }

    public function update(UpdateCountryRequest $request, Country $country)
    {
        $country->update($request->all());

        return redirect()->route('admin.countries.index');
    }

    public function show(Country $country)
    {
        abort_if(Gate::denies('country_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        // تحميل العلاقات الصحيحة
        $country->load([
            'regions',
            'governorates',
            'cities',
            'branch',
            'responsibleUser'
        ]);

        return view('admin.countries.show', compact('country'));
    }

    public function destroy(Country $country)
    {
        abort_if(Gate::denies('country_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $country->delete();

        return back();
    }

    public function massDestroy(MassDestroyCountryRequest $request)
    {
        Country::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}