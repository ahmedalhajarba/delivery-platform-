<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyBranchSectionRequest;
use App\Http\Requests\StoreBranchSectionRequest;
use App\Http\Requests\UpdateBranchSectionRequest;
use App\Models\BranchSection;
use App\Models\User;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BranchSectionsController extends Controller
{
    public function __construct()
    {
        $this->middleware('check.permission:branch_section_access')->only('index');
        $this->middleware('check.permission:branch_section_create')->only(['create', 'store']);
        $this->middleware('check.permission:branch_section_edit')->only(['edit', 'update']);
        $this->middleware('check.permission:branch_section_show')->only('show');
        $this->middleware('check.permission:branch_section_delete')->only(['destroy', 'massDestroy']);
    }
    public function index()
    {
        $branchSections = BranchSection::with(['user'])->get();
        return view('admin.branchSections.index', compact('branchSections'));
    }

    public function create()
    {
        $users = User::all()->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');
        return view('admin.branchSections.create', compact('users'));
    }

    public function store(StoreBranchSectionRequest $request)
    {
        $branchSection = BranchSection::create($request->all());

        return redirect()->route('admin.branch-sections.index');
    }

    public function edit(BranchSection $branchSection)
    {
        $users = User::all()->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');
        $branchSection->load('user');
        return view('admin.branchSections.edit', compact('users', 'branchSection'));
    }

    public function update(UpdateBranchSectionRequest $request, BranchSection $branchSection)
    {
        $branchSection->update($request->all());

        return redirect()->route('admin.branch-sections.index');
    }

    public function show(BranchSection $branchSection)
    {
        $branchSection->load('user');
        return view('admin.branchSections.show', compact('branchSection'));
    }

    public function destroy(BranchSection $branchSection)
    {
        $branchSection->delete();
        return back();
    }

    public function massDestroy(MassDestroyBranchSectionRequest $request)
    {
        BranchSection::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
