<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchCategoryRequest;
use App\Http\Requests\UpdateBranchCategoryRequest;
use App\Http\Resources\Admin\BranchCategoryResource;
use App\Models\BranchCategory;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BranchCategoriesApiController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('branch_category_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new BranchCategoryResource(BranchCategory::all());
    }

    public function store(StoreBranchCategoryRequest $request)
    {
        $branchCategory = BranchCategory::create($request->all());

        return (new BranchCategoryResource($branchCategory))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(BranchCategory $branchCategory)
    {
        abort_if(Gate::denies('branch_category_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new BranchCategoryResource($branchCategory);
    }

    public function update(UpdateBranchCategoryRequest $request, BranchCategory $branchCategory)
    {
        $branchCategory->update($request->all());

        return (new BranchCategoryResource($branchCategory))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(BranchCategory $branchCategory)
    {
        abort_if(Gate::denies('branch_category_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branchCategory->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
