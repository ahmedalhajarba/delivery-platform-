<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyVillageRequest;
use App\Http\Requests\StoreVillageRequest;
use App\Http\Requests\UpdateVillageRequest;
use App\Models\Governorate;
use App\Models\Village;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VillagesController extends Controller
{
    public function __construct()
    {
        $this->middleware('check.permission:village_access')->only('index');
        $this->middleware('check.permission:village_create')->only(['create', 'store']);
        $this->middleware('check.permission:village_edit')->only(['edit', 'update']);
        $this->middleware('check.permission:village_show')->only('show');
        $this->middleware('check.permission:village_delete')->only(['destroy', 'massDestroy']);
    }
    public function index()
    {
        $villages = Village::with(['governorate'])->get();
        return view('admin.villages.index', compact('villages'));
    }

    public function create()
    {
        $governorates = Governorate::all()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');
        return view('admin.villages.create', compact('governorates'));
    }

    public function store(StoreVillageRequest $request)
    {
        $village = Village::create($request->all());

        return redirect()->route('admin.villages.index');
    }

    public function edit(Village $village)
    {
        $governorates = Governorate::all()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');
        $village->load('governorate');
        return view('admin.villages.edit', compact('governorates', 'village'));
    }

    public function update(UpdateVillageRequest $request, Village $village)
    {
        $village->update($request->all());

        return redirect()->route('admin.villages.index');
    }

    public function show(Village $village)
    {
        $village->load('governorate');
        return view('admin.villages.show', compact('village'));
    }

    public function destroy(Village $village)
    {
        $village->delete();
        return back();
    }

    public function massDestroy(MassDestroyVillageRequest $request)
    {
        Village::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
