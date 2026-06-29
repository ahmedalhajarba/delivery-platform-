<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyCompanyTypeRequest;
use App\Http\Requests\StoreCompanyTypeRequest;
use App\Http\Requests\UpdateCompanyTypeRequest;
use App\Models\CompanyType;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CompanyTypeController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('company_type_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $companyTypes = CompanyType::all();

        return view('admin.companyTypes.index', compact('companyTypes'));
    }

    public function create()
    {
        abort_if(Gate::denies('company_type_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.companyTypes.create');
    }

    public function store(StoreCompanyTypeRequest $request)
    {
        $companyType = CompanyType::create($request->all());

        return redirect()->route('admin.company-types.index');
    }

    public function edit(CompanyType $companyType)
    {
        abort_if(Gate::denies('company_type_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.companyTypes.edit', compact('companyType'));
    }

    public function update(UpdateCompanyTypeRequest $request, CompanyType $companyType)
    {
        $companyType->update($request->all());

        return redirect()->route('admin.company-types.index');
    }

    public function show(CompanyType $companyType)
    {
        abort_if(Gate::denies('company_type_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $companyType->load('companyTypeCompanies');

        return view('admin.companyTypes.show', compact('companyType'));
    }

    public function destroy(CompanyType $companyType)
    {
        abort_if(Gate::denies('company_type_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $companyType->delete();

        return back();
    }

    public function massDestroy(MassDestroyCompanyTypeRequest $request)
    {
        CompanyType::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
