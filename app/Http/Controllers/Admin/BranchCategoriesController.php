<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyBranchCategoryRequest;
use App\Http\Requests\StoreBranchCategoryRequest;
use App\Http\Requests\UpdateBranchCategoryRequest;
use App\Models\BranchCategory;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BranchCategoriesController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('branch_category_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branchCategories = BranchCategory::all();

        return view('admin.branchCategories.index', compact('branchCategories'));
    }

    public function create()
    {
        abort_if(Gate::denies('branch_category_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.branchCategories.create');
    }

    public function store(StoreBranchCategoryRequest $request)
    {
        $branchCategory = BranchCategory::create($request->all());

        return redirect()->route('admin.branch-categories.index');
    }

    public function edit(BranchCategory $branchCategory)
    {
        abort_if(Gate::denies('branch_category_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.branchCategories.edit', compact('branchCategory'));
    }

    public function update(UpdateBranchCategoryRequest $request, BranchCategory $branchCategory)
    {
        $branchCategory->update($request->all());

        return redirect()->route('admin.branch-categories.index');
    }

    public function show(BranchCategory $branchCategory)
    {
        abort_if(Gate::denies('branch_category_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branchCategory->load('branchCategoryBranches');

        return view('admin.branchCategories.show', compact('branchCategory'));
    }

    public function destroy(BranchCategory $branchCategory)
    {
        abort_if(Gate::denies('branch_category_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branchCategory->delete();

        return back();
    }

    public function massDestroy(MassDestroyBranchCategoryRequest $request)
    {
        BranchCategory::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
