<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\CsvImportTrait;
use App\Http\Requests\MassDestroyCitiesDestinationRequest;
use App\Http\Requests\StoreCitiesDestinationRequest;
use App\Http\Requests\UpdateCitiesDestinationRequest;
use App\Models\CitiesDestination;
use App\Models\City;
use App\Models\OurPartner;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CitiesDestinationController extends Controller
{
    use CsvImportTrait;

    public function index()
    {
        abort_if(Gate::denies('cities_destination_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $citiesDestinations = CitiesDestination::with(['city', 'partner'])->get();

        return view('admin.citiesDestinations.index', compact('citiesDestinations'));
    }

    public function create()
    {
        abort_if(Gate::denies('cities_destination_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $cities = City::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $partners = OurPartner::pluck('name_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.citiesDestinations.create', compact('cities', 'partners'));
    }

    public function store(StoreCitiesDestinationRequest $request)
    {
        $citiesDestination = CitiesDestination::create($request->all());

        return redirect()->route('admin.cities-destinations.index');
    }

    public function edit(CitiesDestination $citiesDestination)
    {
        abort_if(Gate::denies('cities_destination_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $cities = City::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $partners = OurPartner::pluck('name_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $citiesDestination->load('city', 'partner');

        return view('admin.citiesDestinations.edit', compact('cities', 'partners', 'citiesDestination'));
    }

    public function update(UpdateCitiesDestinationRequest $request, CitiesDestination $citiesDestination)
    {
        $citiesDestination->update($request->all());

        return redirect()->route('admin.cities-destinations.index');
    }

    public function show(CitiesDestination $citiesDestination)
    {
        abort_if(Gate::denies('cities_destination_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $citiesDestination->load('city', 'partner');

        return view('admin.citiesDestinations.show', compact('citiesDestination'));
    }

    public function destroy(CitiesDestination $citiesDestination)
    {
        abort_if(Gate::denies('cities_destination_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $citiesDestination->delete();

        return back();
    }

    public function massDestroy(MassDestroyCitiesDestinationRequest $request)
    {
        CitiesDestination::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
