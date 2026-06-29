<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchTypeRequest;
use App\Http\Requests\UpdateBranchTypeRequest;
use App\Http\Resources\Admin\BranchTypeResource;
use App\Models\BranchType;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BranchTypesApiController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('branch_type_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new BranchTypeResource(BranchType::all());
    }

    public function store(StoreBranchTypeRequest $request)
    {
        $branchType = BranchType::create($request->all());

        return (new BranchTypeResource($branchType))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(BranchType $branchType)
    {
        abort_if(Gate::denies('branch_type_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new BranchTypeResource($branchType);
    }

    public function update(UpdateBranchTypeRequest $request, BranchType $branchType)
    {
        $branchType->update($request->all());

        return (new BranchTypeResource($branchType))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(BranchType $branchType)
    {
        abort_if(Gate::denies('branch_type_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branchType->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
