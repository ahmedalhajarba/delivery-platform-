<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\StorePartnersCategoryRequest;
use App\Http\Requests\UpdatePartnersCategoryRequest;
use App\Http\Resources\Admin\PartnersCategoryResource;
use App\Models\PartnersCategory;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PartnersCategoriesApiController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        abort_if(Gate::denies('partners_category_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new PartnersCategoryResource(PartnersCategory::all());
    }

    public function store(StorePartnersCategoryRequest $request)
    {
        $partnersCategory = PartnersCategory::create($request->all());

        if ($request->input('logo', false)) {
            $partnersCategory->addMedia(storage_path('tmp/uploads/' . basename($request->input('logo'))))->toMediaCollection('logo');
        }

        return (new PartnersCategoryResource($partnersCategory))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(PartnersCategory $partnersCategory)
    {
        abort_if(Gate::denies('partners_category_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new PartnersCategoryResource($partnersCategory);
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

        return (new PartnersCategoryResource($partnersCategory))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(PartnersCategory $partnersCategory)
    {
        abort_if(Gate::denies('partners_category_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $partnersCategory->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
