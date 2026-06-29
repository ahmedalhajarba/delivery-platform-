<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyNeighborhoodRequest;
use App\Http\Requests\StoreNeighborhoodRequest;
use App\Http\Requests\UpdateNeighborhoodRequest;
use App\Models\Branch;
use App\Models\City;
use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class NeighborhoodController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('neighborhood_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $neighborhoods = Neighborhood::with(['city'])->get();

        return view('admin.neighborhoods.index', compact('neighborhoods'));
    }

    public function create()
    {
        abort_if(Gate::denies('neighborhood_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $cities = City::all()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');
        $branches = Branch::query()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');
        $users = User::query()->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.neighborhoods.create', compact('cities', 'branches', 'users'));
    }

    public function store(StoreNeighborhoodRequest $request)
    {
        $neighborhood = Neighborhood::create($request->all());

        return redirect()->route('admin.neighborhoods.index');
    }

    public function edit(Neighborhood $neighborhood)
    {
        abort_if(Gate::denies('neighborhood_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $cities = City::all()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');
        $branches = Branch::query()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');
        $users = User::query()->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $neighborhood->load('city');

        return view('admin.neighborhoods.edit', compact('cities', 'branches', 'users', 'neighborhood'));
    }

    public function update(UpdateNeighborhoodRequest $request, Neighborhood $neighborhood)
    {
        $neighborhood->update($request->all());

        return redirect()->route('admin.neighborhoods.index');
    }

    public function show(Neighborhood $neighborhood)
    {
        abort_if(Gate::denies('neighborhood_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $neighborhood->load('city');

        return view('admin.neighborhoods.show', compact('neighborhood'));
    }

    public function destroy(Neighborhood $neighborhood)
    {
        abort_if(Gate::denies('neighborhood_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $neighborhood->delete();

        return back();
    }

    public function massDestroy(MassDestroyNeighborhoodRequest $request)
    {
        Neighborhood::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
