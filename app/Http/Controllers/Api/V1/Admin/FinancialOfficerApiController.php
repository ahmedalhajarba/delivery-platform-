<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\StoreFinancialOfficerRequest;
use App\Http\Requests\UpdateFinancialOfficerRequest;
use App\Http\Resources\Admin\FinancialOfficerResource;
use App\Models\FinancialOfficer;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FinancialOfficerApiController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        abort_if(Gate::denies('financial_officer_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new FinancialOfficerResource(FinancialOfficer::with(['company', 'city'])->get());
    }

    public function store(StoreFinancialOfficerRequest $request)
    {
        $financialOfficer = FinancialOfficer::create($request->all());

        if ($request->input('authorization_file', false)) {
            $financialOfficer->addMedia(storage_path('tmp/uploads/' . basename($request->input('authorization_file'))))->toMediaCollection('authorization_file');
        }

        return (new FinancialOfficerResource($financialOfficer))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(FinancialOfficer $financialOfficer)
    {
        abort_if(Gate::denies('financial_officer_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new FinancialOfficerResource($financialOfficer->load(['company', 'city']));
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

        return (new FinancialOfficerResource($financialOfficer))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(FinancialOfficer $financialOfficer)
    {
        abort_if(Gate::denies('financial_officer_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $financialOfficer->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
