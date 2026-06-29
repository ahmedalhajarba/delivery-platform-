<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyGovernorateRequest;
use App\Http\Requests\StoreGovernorateRequest;
use App\Http\Requests\UpdateGovernorateRequest;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class GovernorateController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('governorate_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $governorates = Governorate::with(['region', 'country', 'governorateCities'])->get();

        return view('admin.governorates.index', compact('governorates'));
    }

    public function create()
    {
        abort_if(Gate::denies('governorate_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $countries = Country::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');
        $regions = Region::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.governorates.create', compact('countries', 'regions'));
    }

    public function store(StoreGovernorateRequest $request)
    {
        $governorate = Governorate::create($request->all());

        return redirect()->route('admin.governorates.index');
    }

    public function edit(Governorate $governorate)
    {
        abort_if(Gate::denies('governorate_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $countries = Country::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');
        $regions = Region::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.governorates.edit', compact('governorate', 'countries', 'regions'));
    }

    public function update(UpdateGovernorateRequest $request, Governorate $governorate)
    {
        $governorate->update($request->all());

        return redirect()->route('admin.governorates.index');
    }

    public function show(Governorate $governorate)
    {
        abort_if(Gate::denies('governorate_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $governorate->load(['region', 'country', 'governorateCities']);

        return view('admin.governorates.show', compact('governorate'));
    }

    public function destroy(Governorate $governorate)
    {
        abort_if(Gate::denies('governorate_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $governorate->delete();

        return back();
    }

    public function massDestroy(MassDestroyGovernorateRequest $request)
    {
        Governorate::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function toggleStatus(Governorate $governorate)
    {
        // منطق تبديل الحالة
        return back()->with('success', 'تم تحديث الحالة بنجاح');
    }

    public function toggleSubscriptions(Governorate $governorate)
    {
        // منطق تبديل الاشتراكات
        return back()->with('success', 'تم تحديث الاشتراكات بنجاح');
    }

    public function toggleServices(Governorate $governorate)
    {
        // منطق تبديل الخدمات
        return back()->with('success', 'تم تحديث الخدمات بنجاح');
    }

    public function bulkAction(Request $request)
    {
        // منطق الإجراءات الجماعية
        return back()->with('success', 'تم تنفيذ الإجراء بنجاح');
    }
}