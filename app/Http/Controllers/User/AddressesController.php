<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\Neighborhood;
use Illuminate\Http\Request;

class AddressesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->hasPermissionTo('address_access')) {
                abort(403);
            }
            return $next($request);
        });
    }

    public function index()
    {
        $addresses = Address::where('user_id', auth()->id())->latest()->paginate(15);
        return view('user.addresses.index', compact('addresses'));
    }

    public function create()
    {
        abort_if(!auth()->user()->hasPermissionTo('address_create'), 403);

        $countries     = Country::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');
        $governorates  = Governorate::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');
        $cities        = City::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');
        $neighborhoods = Neighborhood::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('user.addresses.create', compact('countries', 'governorates', 'cities', 'neighborhoods'));
    }

    public function store(Request $request)
    {
        abort_if(!auth()->user()->hasPermissionTo('address_create'), 403);

        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'mobile'          => 'required|string|max:20',
            's_mobile'        => 'nullable|string|max:20',
            'country_id'      => 'nullable|integer',
            'governorate_id'  => 'nullable|integer',
            'city_id'         => 'nullable|integer',
            'neighborhood_id' => 'nullable|integer',
            'street'          => 'nullable|string|max:255',
            'type'            => 'nullable|in:0,1',
        ]);

        $validated['user_id'] = auth()->id();
        $address = Address::create($validated);

        return redirect()->route('user.addresses.index')->with('message', trans('global.address_saved'));
    }

    public function edit(Address $address)
    {
        abort_if($address->user_id !== auth()->id(), 403);
        abort_if(!auth()->user()->hasPermissionTo('address_edit'), 403);

        $countries     = Country::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');
        $governorates  = Governorate::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');
        $cities        = City::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');
        $neighborhoods = Neighborhood::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $address->load('country', 'governorate', 'city', 'neighborhood');

        return view('user.addresses.edit', compact('address', 'countries', 'governorates', 'cities', 'neighborhoods'));
    }

    public function update(Request $request, Address $address)
    {
        abort_if($address->user_id !== auth()->id(), 403);
        abort_if(!auth()->user()->hasPermissionTo('address_edit'), 403);

        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'mobile'          => 'required|string|max:20',
            's_mobile'        => 'nullable|string|max:20',
            'country_id'      => 'nullable|integer',
            'governorate_id'  => 'nullable|integer',
            'city_id'         => 'nullable|integer',
            'neighborhood_id' => 'nullable|integer',
            'street'          => 'nullable|string|max:255',
            'type'            => 'nullable|in:0,1',
        ]);

        $address->update($validated);

        return redirect()->route('user.addresses.index')->with('message', trans('global.address_saved'));
    }

    public function destroy(Address $address)
    {
        abort_if($address->user_id !== auth()->id(), 403);
        abort_if(!auth()->user()->hasPermissionTo('address_delete'), 403);

        $address->delete();
        return redirect()->route('user.addresses.index')->with('message', trans('global.address_deleted'));
    }
}
