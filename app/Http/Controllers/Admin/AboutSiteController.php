<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\MassDestroyAboutSiteRequest;
use App\Http\Requests\StoreAboutSiteRequest;
use App\Http\Requests\UpdateAboutSiteRequest;
use App\Models\AboutSite;
use Gate;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class AboutSiteController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        abort_if(Gate::denies('about_site_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $aboutSites = AboutSite::with(['media'])->get();

        return view('admin.aboutSites.index', compact('aboutSites'));
    }

    public function create()
    {
        abort_if(Gate::denies('about_site_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.aboutSites.create');
    }

    public function store(StoreAboutSiteRequest $request)
    {
        $aboutSite = AboutSite::create($request->all());

        if ($request->input('photo_logo', false)) {
            $aboutSite->addMedia(storage_path('tmp/uploads/' . basename($request->input('photo_logo'))))->toMediaCollection('photo_logo');
        }

        if ($media = $request->input('ck-media', false)) {
            Media::whereIn('id', $media)->update(['model_id' => $aboutSite->id]);
        }

        return redirect()->route('admin.about-sites.index');
    }

    public function edit(AboutSite $aboutSite)
    {
        abort_if(Gate::denies('about_site_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.aboutSites.edit', compact('aboutSite'));
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

        return redirect()->route('admin.about-sites.index');
    }

    public function show(AboutSite $aboutSite)
    {
        abort_if(Gate::denies('about_site_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.aboutSites.show', compact('aboutSite'));
    }

    public function destroy(AboutSite $aboutSite)
    {
        abort_if(Gate::denies('about_site_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $aboutSite->delete();

        return back();
    }

    public function massDestroy(MassDestroyAboutSiteRequest $request)
    {
        AboutSite::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function storeCKEditorImages(Request $request)
    {
        abort_if(Gate::denies('about_site_create') && Gate::denies('about_site_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $model         = new AboutSite();
        $model->id     = $request->input('crud_id', 0);
        $model->exists = true;
        $media         = $model->addMediaFromRequest('upload')->toMediaCollection('ck-media');

        return response()->json(['id' => $media->id, 'url' => $media->getUrl()], Response::HTTP_CREATED);
    }
}
