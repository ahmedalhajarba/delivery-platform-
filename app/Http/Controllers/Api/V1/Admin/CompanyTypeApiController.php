<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyTypeRequest;
use App\Http\Requests\UpdateCompanyTypeRequest;
use App\Http\Resources\Admin\CompanyTypeResource;
use App\Models\CompanyType;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CompanyTypeApiController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('company_type_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new CompanyTypeResource(CompanyType::all());
    }

    public function store(StoreCompanyTypeRequest $request)
    {
        $companyType = CompanyType::create($request->all());

        return (new CompanyTypeResource($companyType))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(CompanyType $companyType)
    {
        abort_if(Gate::denies('company_type_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new CompanyTypeResource($companyType);
    }

    public function update(UpdateCompanyTypeRequest $request, CompanyType $companyType)
    {
        $companyType->update($request->all());

        return (new CompanyTypeResource($companyType))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(CompanyType $companyType)
    {
        abort_if(Gate::denies('company_type_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $companyType->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
