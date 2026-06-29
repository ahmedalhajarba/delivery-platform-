<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\MassDestroyFinancialOfficerRequest;
use App\Http\Requests\StoreFinancialOfficerRequest;
use App\Http\Requests\UpdateFinancialOfficerRequest;
use App\Models\City;
use App\Models\Company;
use App\Models\FinancialOfficer;
use Gate;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class FinancialOfficerController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        abort_if(Gate::denies('financial_officer_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $financialOfficers = FinancialOfficer::with(['company', 'city', 'media'])->get();

        $companies = Company::get();

        $cities = City::get();

        return view('admin.financialOfficers.index', compact('financialOfficers', 'companies', 'cities'));
    }

    public function create()
    {
        abort_if(Gate::denies('financial_officer_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $companies = Company::all()->pluck('name_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $cities = City::all()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.financialOfficers.create', compact('companies', 'cities'));
    }

    public function store(StoreFinancialOfficerRequest $request)
    {
        $financialOfficer = FinancialOfficer::create($request->all());

        if ($request->input('authorization_file', false)) {
            $financialOfficer->addMedia(storage_path('tmp/uploads/' . basename($request->input('authorization_file'))))->toMediaCollection('authorization_file');
        }

        if ($media = $request->input('ck-media', false)) {
            Media::whereIn('id', $media)->update(['model_id' => $financialOfficer->id]);
        }

        return redirect()->route('admin.financial-officers.index');
    }

    public function edit(FinancialOfficer $financialOfficer)
    {
        abort_if(Gate::denies('financial_officer_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $companies = Company::all()->pluck('name_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $cities = City::all()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $financialOfficer->load('company', 'city');

        return view('admin.financialOfficers.edit', compact('companies', 'cities', 'financialOfficer'));
    }

    public function update(UpdateFinancialOfficerRequest $request, FinancialOfficer $financialOfficer)
    {
        $financialOfficer->update($request->all());

        if ($request->input('authorization_file', false)) {
            if (!$financialOfficer->authorization_file || $request->input('authorization_file') !== $financialOfficer->authorization_file->file_name) {
                if ($financialOfficer->authorization_file) {
                    $financialOfficer->authorization_file->delete();
                }
                $financialOfficer->addMedia(storage_path('tmp/uploads/' . basename($request->input('authorization_file'))))->toMediaCollection('authorization_file');
            }
        } elseif ($financialOfficer->authorization_file) {
            $financialOfficer->authorization_file->delete();
        }

        return redirect()->route('admin.financial-officers.index');
    }

    public function show(FinancialOfficer $financialOfficer)
    {
        abort_if(Gate::denies('financial_officer_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $financialOfficer->load('company', 'city');

        return view('admin.financialOfficers.show', compact('financialOfficer'));
    }

    public function destroy(FinancialOfficer $financialOfficer)
    {
        abort_if(Gate::denies('financial_officer_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $financialOfficer->delete();

        return back();
    }

    public function massDestroy(MassDestroyFinancialOfficerRequest $request)
    {
        FinancialOfficer::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function storeCKEditorImages(Request $request)
    {
        abort_if(Gate::denies('financial_officer_create') && Gate::denies('financial_officer_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $model         = new FinancialOfficer();
        $model->id     = $request->input('crud_id', 0);
        $model->exists = true;
        $media         = $model->addMediaFromRequest('upload')->toMediaCollection('ck-media');

        return response()->json(['id' => $media->id, 'url' => $media->getUrl()], Response::HTTP_CREATED);
    }
}
