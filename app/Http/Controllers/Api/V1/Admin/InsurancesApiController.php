<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInsuranceRequest;
use App\Http\Requests\UpdateInsuranceRequest;
use App\Http\Resources\Admin\InsuranceResource;
use App\Models\Insurance;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InsurancesApiController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('insurance_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new InsuranceResource(Insurance::with(['order'])->get());
    }

    public function store(StoreInsuranceRequest $request)
    {
        $insurance = Insurance::create($request->all());

        return (new InsuranceResource($insurance))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Insurance $insurance)
    {
        abort_if(Gate::denies('insurance_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new InsuranceResource($insurance->load(['order']));
    }

    public function update(UpdateInsuranceRequest $request, Insurance $insurance)
    {
        $insurance->update($request->all());

        return (new InsuranceResource($insurance))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Insurance $insurance)
    {
        abort_if(Gate::denies('insurance_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $insurance->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
