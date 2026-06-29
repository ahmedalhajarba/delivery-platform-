<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchSectionRequest;
use App\Http\Requests\UpdateBranchSectionRequest;
use App\Http\Resources\Admin\BranchSectionResource;
use App\Models\BranchSection;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BranchSectionsApiController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('branch_section_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new BranchSectionResource(BranchSection::with(['user'])->get());
    }

    public function store(StoreBranchSectionRequest $request)
    {
        $branchSection = BranchSection::create($request->all());

        return (new BranchSectionResource($branchSection))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(BranchSection $branchSection)
    {
        abort_if(Gate::denies('branch_section_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new BranchSectionResource($branchSection->load(['user']));
    }

    public function update(UpdateBranchSectionRequest $request, BranchSection $branchSection)
    {
        $branchSection->update($request->all());

        return (new BranchSectionResource($branchSection))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(BranchSection $branchSection)
    {
        abort_if(Gate::denies('branch_section_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branchSection->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
