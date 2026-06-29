<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyBranchTypeRequest;
use App\Http\Requests\StoreBranchTypeRequest;
use App\Http\Requests\UpdateBranchTypeRequest;
use App\Models\BranchType;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BranchTypesController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('branch_type_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branchTypes = BranchType::all();

        return view('admin.branchTypes.index', compact('branchTypes'));
    }

    public function create()
    {
        abort_if(Gate::denies('branch_type_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.branchTypes.create');
    }

    public function store(StoreBranchTypeRequest $request)
    {
        $branchType = BranchType::create($request->all());

        return redirect()->route('admin.branch-types.index');
    }

    public function edit(BranchType $branchType)
    {
        abort_if(Gate::denies('branch_type_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.branchTypes.edit', compact('branchType'));
    }

    public function update(UpdateBranchTypeRequest $request, BranchType $branchType)
    {
        $branchType->update($request->all());

        return redirect()->route('admin.branch-types.index');
    }

    public function show(BranchType $branchType)
    {
        abort_if(Gate::denies('branch_type_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branchType->load('branchTypeBranches');

        return view('admin.branchTypes.show', compact('branchType'));
    }

    public function destroy(BranchType $branchType)
    {
        abort_if(Gate::denies('branch_type_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branchType->delete();

        return back();
    }

    public function massDestroy(MassDestroyBranchTypeRequest $request)
    {
        BranchType::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
