<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\MassDestroyCurrencyManagementRequest;
use App\Http\Requests\StoreCurrencyManagementRequest;
use App\Http\Requests\UpdateCurrencyManagementRequest;
use App\Models\CurrencyManagement;
use Gate;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class CurrencyManagementController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        abort_if(Gate::denies('currency_management_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $currencyManagements = CurrencyManagement::with(['media'])->get();

        return view('admin.currencyManagements.index', compact('currencyManagements'));
    }

    public function create()
    {
        abort_if(Gate::denies('currency_management_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.currencyManagements.create');
    }

    public function store(StoreCurrencyManagementRequest $request)
    {
        $currencyManagement = CurrencyManagement::create($request->all());

        if ($request->input('logo', false)) {
            $currencyManagement->addMedia(storage_path('tmp/uploads/' . basename($request->input('logo'))))->toMediaCollection('logo');
        }

        if ($media = $request->input('ck-media', false)) {
            Media::whereIn('id', $media)->update(['model_id' => $currencyManagement->id]);
        }

        return redirect()->route('admin.currency-managements.index');
    }

    public function edit(CurrencyManagement $currencyManagement)
    {
        abort_if(Gate::denies('currency_management_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.currencyManagements.edit', compact('currencyManagement'));
    }

    public function update(UpdateCurrencyManagementRequest $request, CurrencyManagement $currencyManagement)
    {
        $currencyManagement->update($request->all());

        if ($request->input('logo', false)) {
            if (!$currencyManagement->logo || $request->input('logo') !== $currencyManagement->logo->file_name) {
                if ($currencyManagement->logo) {
                    $currencyManagement->logo->delete();
                }
                $currencyManagement->addMedia(storage_path('tmp/uploads/' . basename($request->input('logo'))))->toMediaCollection('logo');
            }
        } elseif ($currencyManagement->logo) {
            $currencyManagement->logo->delete();
        }

        return redirect()->route('admin.currency-managements.index');
    }

    public function show(CurrencyManagement $currencyManagement)
    {
        abort_if(Gate::denies('currency_management_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.currencyManagements.show', compact('currencyManagement'));
    }

    public function destroy(CurrencyManagement $currencyManagement)
    {
        abort_if(Gate::denies('currency_management_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $currencyManagement->delete();

        return back();
    }

    public function massDestroy(MassDestroyCurrencyManagementRequest $request)
    {
        CurrencyManagement::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function storeCKEditorImages(Request $request)
    {
        abort_if(Gate::denies('currency_management_create') && Gate::denies('currency_management_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $model         = new CurrencyManagement();
        $model->id     = $request->input('crud_id', 0);
        $model->exists = true;
        $media         = $model->addMediaFromRequest('upload')->toMediaCollection('ck-media');

        return response()->json(['id' => $media->id, 'url' => $media->getUrl()], Response::HTTP_CREATED);
    }
}
