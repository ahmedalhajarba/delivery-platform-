<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\MassDestroyBranchEmployeeRequest;
use App\Http\Requests\StoreBranchEmployeeRequest;
use App\Http\Requests\UpdateBranchEmployeeRequest;
use App\Models\Branch;
use App\Models\BranchEmployee;
use App\Models\City;
use App\Models\Country;
use Gate;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class BranchEmployeesController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        abort_if(Gate::denies('branch_employee_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branchEmployees = BranchEmployee::with(['branch', 'country', 'city', 'media'])->get();

        return view('admin.branchEmployees.index', compact('branchEmployees'));
    }

    public function create()
    {
        abort_if(Gate::denies('branch_employee_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branches = Branch::all()->pluck('number', 'id')->prepend(trans('global.pleaseSelect'), '');

        $countries = Country::all()->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $cities = City::all()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.branchEmployees.create', compact('branches', 'countries', 'cities'));
    }

    public function store(StoreBranchEmployeeRequest $request)
    {
        $branchEmployee = BranchEmployee::create($request->all());

        if ($request->input('image', false)) {
            $branchEmployee->addMedia(storage_path('tmp/uploads/' . basename($request->input('image'))))->toMediaCollection('image');
        }

        if ($media = $request->input('ck-media', false)) {
            Media::whereIn('id', $media)->update(['model_id' => $branchEmployee->id]);
        }

        return redirect()->route('admin.branch-employees.index');
    }

    public function edit(BranchEmployee $branchEmployee)
    {
        abort_if(Gate::denies('branch_employee_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branches = Branch::all()->pluck('number', 'id')->prepend(trans('global.pleaseSelect'), '');

        $countries = Country::all()->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $cities = City::all()->pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $branchEmployee->load('branch', 'country', 'city');

        return view('admin.branchEmployees.edit', compact('branches', 'countries', 'cities', 'branchEmployee'));
    }

    public function update(UpdateBranchEmployeeRequest $request, BranchEmployee $branchEmployee)
    {
        $branchEmployee->update($request->all());

        if ($request->input('image', false)) {
            if (!$branchEmployee->image || $request->input('image') !== $branchEmployee->image->file_name) {
                if ($branchEmployee->image) {
                    $branchEmployee->image->delete();
                }
                $branchEmployee->addMedia(storage_path('tmp/uploads/' . basename($request->input('image'))))->toMediaCollection('image');
            }
        } elseif ($branchEmployee->image) {
            $branchEmployee->image->delete();
        }

        return redirect()->route('admin.branch-employees.index');
    }

    public function show(BranchEmployee $branchEmployee)
    {
        abort_if(Gate::denies('branch_employee_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branchEmployee->load('branch', 'country', 'city');

        return view('admin.branchEmployees.show', compact('branchEmployee'));
    }

    public function destroy(BranchEmployee $branchEmployee)
    {
        abort_if(Gate::denies('branch_employee_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branchEmployee->delete();

        return back();
    }

    public function massDestroy(MassDestroyBranchEmployeeRequest $request)
    {
        BranchEmployee::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function storeCKEditorImages(Request $request)
    {
        abort_if(Gate::denies('branch_employee_create') && Gate::denies('branch_employee_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $model         = new BranchEmployee();
        $model->id     = $request->input('crud_id', 0);
        $model->exists = true;
        $media         = $model->addMediaFromRequest('upload')->toMediaCollection('ck-media');

        return response()->json(['id' => $media->id, 'url' => $media->getUrl()], Response::HTTP_CREATED);
    }
}
