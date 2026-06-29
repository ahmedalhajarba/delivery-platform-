<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyAddressRequest;
use App\Http\Requests\StoreAddressRequest;
use App\Http\Requests\UpdateAddressRequest;
use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\Neighborhood;
use App\Models\User;
// use App\Models\Village;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddressesController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('address_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $addresses = Address::with(['user', 'country', 'governorate', 'city'])->get();

        return view('admin.addresses.index', compact('addresses'));
    }

    public function create()
    {
        abort_if(Gate::denies('address_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $users = User::all()->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $countries = Country::all()->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $governorates = Governorate::all()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $cities = City::all()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $neighborhoods = Neighborhood::all()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.addresses.create', compact('users', 'countries', 'governorates', 'cities', 'neighborhoods'));
    }

    public function store(StoreAddressRequest $request)
    {
        $address = Address::create($request->all());

        return redirect()->route('admin.addresses.index');
    }

    public function edit(Address $address)
    {
        abort_if(Gate::denies('address_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $users = User::all()->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $countries = Country::all()->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $governorates = Governorate::all()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $cities = City::all()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $neighborhoods = Neighborhood::all()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $address->load('user', 'country', 'governorate', 'city', 'neighborhood');

        return view('admin.addresses.edit', compact('users', 'countries', 'governorates', 'cities', 'neighborhoods', 'address'));
    }

    public function update(UpdateAddressRequest $request, Address $address)
    {
        $address->update($request->all());

        return redirect()->route('admin.addresses.index');
    }

    public function show(Address $address)
    {
        abort_if(Gate::denies('address_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $address->load('user', 'country', 'governorate', 'city', 'neighborhood');

        return view('admin.addresses.show', compact('address'));
    }

    public function destroy(Address $address)
    {
        abort_if(Gate::denies('address_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $address->delete();

        return back();
    }

    public function massDestroy(MassDestroyAddressRequest $request)
    {
        Address::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
