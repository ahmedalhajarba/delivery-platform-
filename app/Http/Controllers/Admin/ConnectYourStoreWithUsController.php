<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\MassDestroyConnectYourStoreWithUsRequest;
use App\Http\Requests\StoreConnectYourStoreWithUsRequest;
use App\Http\Requests\UpdateConnectYourStoreWithUsRequest;
use App\Models\ConnectYourStoreWithUs;
use Gate;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

class ConnectYourStoreWithUsController extends Controller
{
    use MediaUploadingTrait;

    public function index(Request $request)
    {
        abort_if(Gate::denies('connect_your_store_with_us_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->ajax()) {
            $query = ConnectYourStoreWithUs::query()->select(sprintf('%s.*', (new ConnectYourStoreWithUs())->table));
            $table = Datatables::of($query);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $viewGate = 'connect_your_store_with_us_show';
                $editGate = 'connect_your_store_with_us_edit';
                $deleteGate = 'connect_your_store_with_us_delete';
                $crudRoutePart = 'connect-your-store-withuses';

                return view('partials.datatablesActions', compact(
                'viewGate',
                'editGate',
                'deleteGate',
                'crudRoutePart',
                'row'
            ));
            });

            $table->editColumn('id', function ($row) {
                return $row->id ? $row->id : '';
            });
            $table->editColumn('title', function ($row) {
                return $row->title ? $row->title : '';
            });

            $table->rawColumns(['actions', 'placeholder']);

            return $table->make(true);
        }

        return view('admin.connectYourStoreWithuses.index');
    }

    public function create()
    {
        abort_if(Gate::denies('connect_your_store_with_us_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.connectYourStoreWithuses.create');
    }

    public function store(StoreConnectYourStoreWithUsRequest $request)
    {
        $connectYourStoreWithUs = ConnectYourStoreWithUs::create($request->all());

        if ($media = $request->input('ck-media', false)) {
            Media::whereIn('id', $media)->update(['model_id' => $connectYourStoreWithUs->id]);
        }

        return redirect()->route('admin.connect-your-store-withuses.index');
    }

    public function edit(ConnectYourStoreWithUs $connectYourStoreWithUs)
    {
        abort_if(Gate::denies('connect_your_store_with_us_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.connectYourStoreWithuses.edit', compact('connectYourStoreWithUs'));
    }

    public function update(UpdateConnectYourStoreWithUsRequest $request, ConnectYourStoreWithUs $connectYourStoreWithUs)
    {
        $connectYourStoreWithUs->update($request->all());

        return redirect()->route('admin.connect-your-store-withuses.index');
    }

    public function show(ConnectYourStoreWithUs $connectYourStoreWithUs)
    {
        abort_if(Gate::denies('connect_your_store_with_us_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.connectYourStoreWithuses.show', compact('connectYourStoreWithUs'));
    }

    public function destroy(ConnectYourStoreWithUs $connectYourStoreWithUs)
    {
        abort_if(Gate::denies('connect_your_store_with_us_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $connectYourStoreWithUs->delete();

        return back();
    }

    public function massDestroy(MassDestroyConnectYourStoreWithUsRequest $request)
    {
        ConnectYourStoreWithUs::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function storeCKEditorImages(Request $request)
    {
        abort_if(Gate::denies('connect_your_store_with_us_create') && Gate::denies('connect_your_store_with_us_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $model         = new ConnectYourStoreWithUs();
        $model->id     = $request->input('crud_id', 0);
        $model->exists = true;
        $media         = $model->addMediaFromRequest('upload')->toMediaCollection('ck-media');

        return response()->json(['id' => $media->id, 'url' => $media->getUrl()], Response::HTTP_CREATED);
    }
}
