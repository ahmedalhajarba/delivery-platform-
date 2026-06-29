<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\StoreAboutSiteRequest;
use App\Http\Requests\UpdateAboutSiteRequest;
use App\Http\Resources\Admin\AboutSiteResource;
use App\Models\AboutSite;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AboutSiteApiController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        abort_if(Gate::denies('about_site_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new AboutSiteResource(AboutSite::all());
    }

    public function store(StoreAboutSiteRequest $request)
    {
        $aboutSite = AboutSite::create($request->all());

        if ($request->input('photo_logo', false)) {
            $aboutSite->addMedia(storage_path('tmp/uploads/' . basename($request->input('photo_logo'))))->toMediaCollection('photo_logo');
        }

        return (new AboutSiteResource($aboutSite))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(AboutSite $aboutSite)
    {
        abort_if(Gate::denies('about_site_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new AboutSiteResource($aboutSite);
    }

    public function update(UpdateAboutSiteRequest $request, AboutSite $aboutSite)
    {
        $aboutSite->update($request->all());

        if ($request->input('photo_logo', false)) {
            if (!$aboutSite->photo_logo || $request->input('photo_logo') !== $aboutSite->photo_logo->file_name) {
                if ($aboutSite->photo_logo) {
                    $aboutSite->photo_logo->delete();
                }
                $aboutSite->addMedia(storage_path('tmp/uploads/' . basename($request->input('photo_logo'))))->toMediaCollection('photo_logo');
            }
        } elseif ($aboutSite->photo_logo) {
            $aboutSite->photo_logo->delete();
        }

        return (new AboutSiteResource($aboutSite))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(AboutSite $aboutSite)
    {
        abort_if(Gate::denies('about_site_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $aboutSite->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
