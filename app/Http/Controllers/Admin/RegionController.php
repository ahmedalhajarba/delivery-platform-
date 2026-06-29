<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class RegionController extends Controller
{
    /**
     * عرض قائمة المناطق
     */
    public function index()
    {
        abort_if(Gate::denies('region_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $regions = Region::with(['country', 'governorates'])->get();

        return view('admin.regions.index', compact('regions'));
    }

    /**
     * عرض نموذج إضافة منطقة جديدة
     */
    public function create()
    {
        abort_if(Gate::denies('region_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $countries = Country::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.regions.create', compact('countries'));
    }

    /**
     * حفظ منطقة جديدة
     */
    public function store(Request $request)
    {
        abort_if(Gate::denies('region_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $request->validate([
            'title_ar' => 'required|string|max:255',
            'title_en' => 'required|string|max:255',
            'country_id' => 'required|exists:countries,id',
        ]);

        $slug = $request->slug ?? \Illuminate\Support\Str::slug($request->title_en);

        $region = Region::create([
            'title_ar' => $request->title_ar,
            'title_en' => $request->title_en,
            'slug' => $slug,
            'country_id' => $request->country_id,
        ]);

        return redirect()->route('admin.regions.index')
            ->with('success', 'تم إضافة المنطقة بنجاح');
    }

    /**
     * عرض نموذج تعديل منطقة
     */
    public function edit(Region $region)
    {
        abort_if(Gate::denies('region_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $countries = Country::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.regions.edit', compact('region', 'countries'));
    }

    /**
     * تحديث منطقة
     */
    public function update(Request $request, Region $region)
    {
        abort_if(Gate::denies('region_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $request->validate([
            'title_ar' => 'required|string|max:255',
            'title_en' => 'required|string|max:255',
            'country_id' => 'required|exists:countries,id',
        ]);

        $slug = $request->slug ?? \Illuminate\Support\Str::slug($request->title_en);

        $region->update([
            'title_ar' => $request->title_ar,
            'title_en' => $request->title_en,
            'slug' => $slug,
            'country_id' => $request->country_id,
        ]);

        return redirect()->route('admin.regions.index')
            ->with('success', 'تم تحديث المنطقة بنجاح');
    }

    /**
     * عرض تفاصيل منطقة
     */
    public function show(Region $region)
    {
        abort_if(Gate::denies('region_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        // تحميل العلاقات الصحيحة
        $region->load(['country', 'governorates']);

        return view('admin.regions.show', compact('region'));
    }

    /**
     * حذف منطقة
     */
    public function destroy(Region $region)
    {
        abort_if(Gate::denies('region_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $region->delete();

        return redirect()->route('admin.regions.index')
            ->with('success', 'تم حذف المنطقة بنجاح');
    }
}