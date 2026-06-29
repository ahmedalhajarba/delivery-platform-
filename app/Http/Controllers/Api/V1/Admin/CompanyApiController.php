<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Resources\Admin\CompanyResource;
use App\Models\Company;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CompanyApiController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        abort_if(Gate::denies('company_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new CompanyResource(Company::with(['city', 'user', 'company_type'])->get());
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

        return (new CompanyResource($company))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Company $company)
    {
        abort_if(Gate::denies('company_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new CompanyResource($company->load(['city', 'user', 'company_type']));
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

        return (new CompanyResource($company))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Company $company)
    {
        abort_if(Gate::denies('company_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $company->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
