<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class GovernoratesController extends Controller
{
    /**
     * عرض قائمة المحافظات
     */
    public function index()
    {
        abort_if(Gate::denies('governorate_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $governorates = Governorate::with(['region', 'country', 'governorateCities'])->get();

        return view('admin.governorates.index', compact('governorates'));
    }

    /**
     * عرض نموذج إضافة محافظة جديدة
     */
    public function create()
    {
        abort_if(Gate::denies('governorate_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        // ===== جلب البيانات =====
        $countries = Country::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');
        $regions = Region::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        // ===== DEBUG: للتحقق =====
        // dd($countries, $regions);

        return view('admin.governorates.create', compact('countries', 'regions'));
    }

    /**
     * حفظ محافظة جديدة
     */
    public function store(Request $request)
    {
        abort_if(Gate::denies('governorate_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $request->validate([
            'title_ar' => 'required|string|max:255',
            'title_en' => 'required|string|max:255',
            'country_id' => 'required|exists:countries,id',
            'region_id' => 'required|exists:regions,id',
        ]);

        $slug = $request->slug ?? \Illuminate\Support\Str::slug($request->title_en);

        Governorate::create([
            'title_ar' => $request->title_ar,
            'title_en' => $request->title_en,
            'slug' => $slug,
            'country_id' => $request->country_id,
            'region_id' => $request->region_id,
        ]);

        return redirect()->route('admin.governorates.index')
            ->with('success', 'تم إضافة المحافظة بنجاح');
    }

    /**
     * عرض نموذج تعديل محافظة
     */
    public function edit(Governorate $governorate)
    {
        abort_if(Gate::denies('governorate_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $countries = Country::pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');
        $regions = Region::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.governorates.edit', compact('governorate', 'countries', 'regions'));
    }

    /**
     * تحديث محافظة
     */
    public function update(Request $request, Governorate $governorate)
    {
        abort_if(Gate::denies('governorate_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $request->validate([
            'title_ar' => 'required|string|max:255',
            'title_en' => 'required|string|max:255',
            'country_id' => 'required|exists:countries,id',
            'region_id' => 'required|exists:regions,id',
        ]);

        $slug = $request->slug ?? \Illuminate\Support\Str::slug($request->title_en);

        $governorate->update([
            'title_ar' => $request->title_ar,
            'title_en' => $request->title_en,
            'slug' => $slug,
            'country_id' => $request->country_id,
            'region_id' => $request->region_id,
        ]);

        return redirect()->route('admin.governorates.index')
            ->with('success', 'تم تحديث المحافظة بنجاح');
    }

    /**
     * عرض تفاصيل محافظة
     */
    public function show(Governorate $governorate)
    {
        abort_if(Gate::denies('governorate_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $governorate->load(['region', 'country', 'governorateCities']);

        return view('admin.governorates.show', compact('governorate'));
    }

    /**
     * حذف محافظة
     */
    public function destroy(Governorate $governorate)
    {
        abort_if(Gate::denies('governorate_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $governorate->delete();

        return redirect()->route('admin.governorates.index')
            ->with('success', 'تم حذف المحافظة بنجاح');
    }
}