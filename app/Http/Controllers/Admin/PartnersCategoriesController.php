<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\MassDestroyPartnersCategoryRequest;
use App\Http\Requests\StorePartnersCategoryRequest;
use App\Http\Requests\UpdatePartnersCategoryRequest;
use App\Models\PartnersCategory;
use Gate;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class PartnersCategoriesController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        abort_if(Gate::denies('partners_category_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $partnersCategories = PartnersCategory::with(['media'])->get();

        return view('admin.partnersCategories.index', compact('partnersCategories'));
    }

    public function create()
    {
        abort_if(Gate::denies('partners_category_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.partnersCategories.create');
    }

    public function store(StorePartnersCategoryRequest $request)
    {
        $partnersCategory = PartnersCategory::create($request->all());

        if ($request->input('logo', false)) {
            $partnersCategory->addMedia(storage_path('tmp/uploads/' . basename($request->input('logo'))))->toMediaCollection('logo');
        }

        if ($media = $request->input('ck-media', false)) {
            Media::whereIn('id', $media)->update(['model_id' => $partnersCategory->id]);
        }

        return redirect()->route('admin.partners-categories.index');
    }

    public function edit(PartnersCategory $partnersCategory)
    {
        abort_if(Gate::denies('partners_category_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.partnersCategories.edit', compact('partnersCategory'));
    }

    public function update(UpdatePartnersCategoryRequest $request, PartnersCategory $partnersCategory)
    {
        $partnersCategory->update($request->all());

        if ($request->input('logo', false)) {
            if (!$partnersCategory->logo || $request->input('logo') !== $partnersCategory->logo->file_name) {
                if ($partnersCategory->logo) {
                    $partnersCategory->logo->delete();
                }
                $partnersCategory->addMedia(storage_path('tmp/uploads/' . basename($request->input('logo'))))->toMediaCollection('logo');
            }
        } elseif ($partnersCategory->logo) {
            $partnersCategory->logo->delete();
        }

        return redirect()->route('admin.partners-categories.index');
    }

    public function show(PartnersCategory $partnersCategory)
    {
        abort_if(Gate::denies('partners_category_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $partnersCategory->load('partnerCategoryOurPartners');

        return view('admin.partnersCategories.show', compact('partnersCategory'));
    }

    public function destroy(PartnersCategory $partnersCategory)
    {
        abort_if(Gate::denies('partners_category_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $partnersCategory->delete();

        return back();
    }

    public function massDestroy(MassDestroyPartnersCategoryRequest $request)
    {
        PartnersCategory::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function storeCKEditorImages(Request $request)
    {
        abort_if(Gate::denies('partners_category_create') && Gate::denies('partners_category_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $model         = new PartnersCategory();
        $model->id     = $request->input('crud_id', 0);
        $model->exists = true;
        $media         = $model->addMediaFromRequest('upload')->toMediaCollection('ck-media');

        return response()->json(['id' => $media->id, 'url' => $media->getUrl()], Response::HTTP_CREATED);
    }
}
