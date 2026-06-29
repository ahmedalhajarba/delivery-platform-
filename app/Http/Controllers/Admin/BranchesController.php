<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyBranchRequest;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use App\Models\BranchCategory;
use App\Models\BranchType;
use App\Models\City;
use App\Models\Country;
use App\Models\User;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BranchesController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('branch_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branches = Branch::with(['country', 'cities', 'user', 'branch_type', 'branch_category'])->get();

        return view('admin.branches.index', compact('branches'));
    }

    public function create()
    {
        abort_if(Gate::denies('branch_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $countries = Country::all()->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $cities = City::all()->pluck('title_ar', 'id');

        $users = User::all()->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $branch_types = BranchType::all()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $branch_categories = BranchCategory::all()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.branches.create', compact('countries', 'cities', 'users', 'branch_types', 'branch_categories'));
    }

    public function store(StoreBranchRequest $request)
    {
        $branch = Branch::create($request->all());
        $branch->cities()->sync($request->input('cities', []));

        return redirect()->route('admin.branches.index');
    }

    public function edit(Branch $branch)
    {
        abort_if(Gate::denies('branch_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $countries = Country::all()->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $cities = City::all()->pluck('title_ar', 'id');

        $users = User::all()->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $branch_types = BranchType::all()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $branch_categories = BranchCategory::all()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $branch->load('country', 'cities', 'user', 'branch_type', 'branch_category');

        return view('admin.branches.edit', compact('countries', 'cities', 'users', 'branch_types', 'branch_categories', 'branch'));
    }

    public function update(UpdateBranchRequest $request, Branch $branch)
    {
        $branch->update($request->all());
        $branch->cities()->sync($request->input('cities', []));

        return redirect()->route('admin.branches.index');
    }

    public function show(Branch $branch)
    {
        abort_if(Gate::denies('branch_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branch->load('country', 'cities', 'user', 'branch_type', 'branch_category', 'branchBranchEmployees');

        return view('admin.branches.show', compact('branch'));
    }

    public function destroy(Branch $branch)
    {
        abort_if(Gate::denies('branch_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branch->delete();

        return back();
    }

    public function massDestroy(MassDestroyBranchRequest $request)
    {
        Branch::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
