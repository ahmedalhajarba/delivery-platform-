<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\MassDestroyHowDoWorkRequest;
use App\Http\Requests\StoreHowDoWorkRequest;
use App\Http\Requests\UpdateHowDoWorkRequest;
use App\Models\HowDoWork;
use Gate;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class HowDoWorkController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        abort_if(Gate::denies('how_do_work_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $howDoWorks = HowDoWork::all();

        return view('admin.howDoWorks.index', compact('howDoWorks'));
    }

    public function create()
    {
        abort_if(Gate::denies('how_do_work_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.howDoWorks.create');
    }

    public function store(StoreHowDoWorkRequest $request)
    {
        $howDoWork = HowDoWork::create($request->all());

        if ($media = $request->input('ck-media', false)) {
            Media::whereIn('id', $media)->update(['model_id' => $howDoWork->id]);
        }

        return redirect()->route('admin.how-do-works.index');
    }

    public function edit(HowDoWork $howDoWork)
    {
        abort_if(Gate::denies('how_do_work_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.howDoWorks.edit', compact('howDoWork'));
    }

    public function update(UpdateHowDoWorkRequest $request, HowDoWork $howDoWork)
    {
        $howDoWork->update($request->all());

        return redirect()->route('admin.how-do-works.index');
    }

    public function show(HowDoWork $howDoWork)
    {
        abort_if(Gate::denies('how_do_work_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.howDoWorks.show', compact('howDoWork'));
    }

    public function destroy(HowDoWork $howDoWork)
    {
        abort_if(Gate::denies('how_do_work_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $howDoWork->delete();

        return back();
    }

    public function massDestroy(MassDestroyHowDoWorkRequest $request)
    {
        HowDoWork::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function storeCKEditorImages(Request $request)
    {
        abort_if(Gate::denies('how_do_work_create') && Gate::denies('how_do_work_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $model         = new HowDoWork();
        $model->id     = $request->input('crud_id', 0);
        $model->exists = true;
        $media         = $model->addMediaFromRequest('upload')->toMediaCollection('ck-media');

        return response()->json(['id' => $media->id, 'url' => $media->getUrl()], Response::HTTP_CREATED);
    }
}
