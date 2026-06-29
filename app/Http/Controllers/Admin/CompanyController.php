<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\MassDestroyCompanyRequest;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\City;
use App\Models\Company;
use App\Models\CompanyType;
use App\Models\User;
use Gate;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class CompanyController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        abort_if(Gate::denies('company_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $companies = Company::with(['city', 'user', 'company_type', 'media'])->get();

        return view('admin.companies.index', compact('companies'));
    }

    public function create()
    {
        abort_if(Gate::denies('company_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $cities = City::all()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $users = User::all()->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $company_types = CompanyType::all()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.companies.create', compact('cities', 'users', 'company_types'));
    }

    public function store(StoreCompanyRequest $request)
    {
        $company = Company::create($request->all());

        if ($request->input('image_cr', false)) {
            $company->addMedia(storage_path('tmp/uploads/' . basename($request->input('image_cr'))))->toMediaCollection('image_cr');
        }

        if ($request->input('vat', false)) {
            $company->addMedia(storage_path('tmp/uploads/' . basename($request->input('vat'))))->toMediaCollection('vat');
        }

        if ($request->input('proof_tax_exemption', false)) {
            $company->addMedia(storage_path('tmp/uploads/' . basename($request->input('proof_tax_exemption'))))->toMediaCollection('proof_tax_exemption');
        }

        if ($media = $request->input('ck-media', false)) {
            Media::whereIn('id', $media)->update(['model_id' => $company->id]);
        }

        return redirect()->route('admin.companies.index');
    }

    public function edit(Company $company)
    {
        abort_if(Gate::denies('company_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $cities = City::all()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $users = User::all()->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $company_types = CompanyType::all()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $company->load('city', 'user', 'company_type');

        return view('admin.companies.edit', compact('cities', 'users', 'company_types', 'company'));
    }

    public function update(UpdateCompanyRequest $request, Company $company)
    {
        $company->update($request->all());

        if ($request->input('image_cr', false)) {
            if (!$company->image_cr || $request->input('image_cr') !== $company->image_cr->file_name) {
                if ($company->image_cr) {
                    $company->image_cr->delete();
                }
                $company->addMedia(storage_path('tmp/uploads/' . basename($request->input('image_cr'))))->toMediaCollection('image_cr');
            }
        } elseif ($company->image_cr) {
            $company->image_cr->delete();
        }

        if ($request->input('vat', false)) {
            if (!$company->vat || $request->input('vat') !== $company->vat->file_name) {
                if ($company->vat) {
                    $company->vat->delete();
                }
                $company->addMedia(storage_path('tmp/uploads/' . basename($request->input('vat'))))->toMediaCollection('vat');
            }
        } elseif ($company->vat) {
            $company->vat->delete();
        }

        if ($request->input('proof_tax_exemption', false)) {
            if (!$company->proof_tax_exemption || $request->input('proof_tax_exemption') !== $company->proof_tax_exemption->file_name) {
                if ($company->proof_tax_exemption) {
                    $company->proof_tax_exemption->delete();
                }
                $company->addMedia(storage_path('tmp/uploads/' . basename($request->input('proof_tax_exemption'))))->toMediaCollection('proof_tax_exemption');
            }
        } elseif ($company->proof_tax_exemption) {
            $company->proof_tax_exemption->delete();
        }

        return redirect()->route('admin.companies.index');
    }

    public function show(Company $company)
    {
        abort_if(Gate::denies('company_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $company->load('city', 'user', 'company_type', 'companyFinancialOfficers');

        return view('admin.companies.show', compact('company'));
    }

    public function destroy(Company $company)
    {
        abort_if(Gate::denies('company_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $company->delete();

        return back();
    }

    public function massDestroy(MassDestroyCompanyRequest $request)
    {
        Company::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function storeCKEditorImages(Request $request)
    {
        abort_if(Gate::denies('company_create') && Gate::denies('company_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $model         = new Company();
        $model->id     = $request->input('crud_id', 0);
        $model->exists = true;
        $media         = $model->addMediaFromRequest('upload')->toMediaCollection('ck-media');

        return response()->json(['id' => $media->id, 'url' => $media->getUrl()], Response::HTTP_CREATED);
    }
}
