<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Http\Resources\Admin\BranchResource;
use App\Models\Branch;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BranchesApiController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('branch_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new BranchResource(Branch::with(['country', 'cities', 'user', 'branch_type', 'branch_category'])->get());
    }

    public function store(StoreBranchRequest $request)
    {
        $branch = Branch::create($request->all());
        $branch->cities()->sync($request->input('cities', []));

        return (new BranchResource($branch))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Branch $branch)
    {
        abort_if(Gate::denies('branch_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new BranchResource($branch->load(['country', 'cities', 'user', 'branch_type', 'branch_category']));
    }

    public function update(UpdateBranchRequest $request, Branch $branch)
    {
        $branch->update($request->all());
        $branch->cities()->sync($request->input('cities', []));

        return (new BranchResource($branch))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Branch $branch)
    {
        abort_if(Gate::denies('branch_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branch->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
