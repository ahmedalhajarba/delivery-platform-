<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\MassDestroyOurAppRequest;
use App\Http\Requests\StoreOurAppRequest;
use App\Http\Requests\UpdateOurAppRequest;
use App\Models\OurApp;
use Gate;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class OurAppsController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        abort_if(Gate::denies('our_app_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $ourApps = OurApp::with(['media'])->get();

        return view('admin.ourApps.index', compact('ourApps'));
    }

    public function create()
    {
        abort_if(Gate::denies('our_app_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.ourApps.create');
    }

    public function store(StoreOurAppRequest $request)
    {
        $ourApp = OurApp::create($request->all());

        if ($request->input('image', false)) {
            $ourApp->addMedia(storage_path('tmp/uploads/' . basename($request->input('image'))))->toMediaCollection('image');
        }

        if ($media = $request->input('ck-media', false)) {
            Media::whereIn('id', $media)->update(['model_id' => $ourApp->id]);
        }

        return redirect()->route('admin.our-apps.index');
    }

    public function edit(OurApp $ourApp)
    {
        abort_if(Gate::denies('our_app_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.ourApps.edit', compact('ourApp'));
    }

    public function update(UpdateOurAppRequest $request, OurApp $ourApp)
    {
        $ourApp->update($request->all());

        if ($request->input('image', false)) {
            if (!$ourApp->image || $request->input('image') !== $ourApp->image->file_name) {
                if ($ourApp->image) {
                    $ourApp->image->delete();
                }
                $ourApp->addMedia(storage_path('tmp/uploads/' . basename($request->input('image'))))->toMediaCollection('image');
            }
        } elseif ($ourApp->image) {
            $ourApp->image->delete();
        }

        return redirect()->route('admin.our-apps.index');
    }

    public function show(OurApp $ourApp)
    {
        abort_if(Gate::denies('our_app_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.ourApps.show', compact('ourApp'));
    }

    public function destroy(OurApp $ourApp)
    {
        abort_if(Gate::denies('our_app_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $ourApp->delete();

        return back();
    }

    public function massDestroy(MassDestroyOurAppRequest $request)
    {
        OurApp::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function storeCKEditorImages(Request $request)
    {
        abort_if(Gate::denies('our_app_create') && Gate::denies('our_app_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $model         = new OurApp();
        $model->id     = $request->input('crud_id', 0);
        $model->exists = true;
        $media         = $model->addMediaFromRequest('upload')->toMediaCollection('ck-media');

        return response()->json(['id' => $media->id, 'url' => $media->getUrl()], Response::HTTP_CREATED);
    }
}
