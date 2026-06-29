<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\MassDestroyMissingoalRequest;
use App\Http\Requests\StoreMissingoalRequest;
use App\Http\Requests\UpdateMissingoalRequest;
use App\Models\Missingoal;
use Gate;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class MissingoalController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        abort_if(Gate::denies('missingoal_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $missingoals = Missingoal::all();

        return view('admin.missingoals.index', compact('missingoals'));
    }

    public function create()
    {
        abort_if(Gate::denies('missingoal_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.missingoals.create');
    }

    public function store(StoreMissingoalRequest $request)
    {
        $missingoal = Missingoal::create($request->all());

        if ($media = $request->input('ck-media', false)) {
            Media::whereIn('id', $media)->update(['model_id' => $missingoal->id]);
        }

        return redirect()->route('admin.missingoals.index');
    }

    public function edit(Missingoal $missingoal)
    {
        abort_if(Gate::denies('missingoal_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.missingoals.edit', compact('missingoal'));
    }

    public function update(UpdateMissingoalRequest $request, Missingoal $missingoal)
    {
        $missingoal->update($request->all());

        return redirect()->route('admin.missingoals.index');
    }

    public function show(Missingoal $missingoal)
    {
        abort_if(Gate::denies('missingoal_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.missingoals.show', compact('missingoal'));
    }

    public function destroy(Missingoal $missingoal)
    {
        abort_if(Gate::denies('missingoal_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $missingoal->delete();

        return back();
    }

    public function massDestroy(MassDestroyMissingoalRequest $request)
    {
        Missingoal::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function storeCKEditorImages(Request $request)
    {
        abort_if(Gate::denies('missingoal_create') && Gate::denies('missingoal_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $model         = new Missingoal();
        $model->id     = $request->input('crud_id', 0);
        $model->exists = true;
        $media         = $model->addMediaFromRequest('upload')->toMediaCollection('ck-media');

        return response()->json(['id' => $media->id, 'url' => $media->getUrl()], Response::HTTP_CREATED);
    }
}
