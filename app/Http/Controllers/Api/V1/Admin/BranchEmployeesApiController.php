<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\StoreBranchEmployeeRequest;
use App\Http\Requests\UpdateBranchEmployeeRequest;
use App\Http\Resources\Admin\BranchEmployeeResource;
use App\Models\BranchEmployee;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BranchEmployeesApiController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        abort_if(Gate::denies('branch_employee_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new BranchEmployeeResource(BranchEmployee::with(['branch', 'country', 'city'])->get());
    }

    public function store(StoreBranchEmployeeRequest $request)
    {
        $branchEmployee = BranchEmployee::create($request->all());

        if ($request->input('image', false)) {
            $branchEmployee->addMedia(storage_path('tmp/uploads/' . basename($request->input('image'))))->toMediaCollection('image');
        }

        return (new BranchEmployeeResource($branchEmployee))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(BranchEmployee $branchEmployee)
    {
        abort_if(Gate::denies('branch_employee_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new BranchEmployeeResource($branchEmployee->load(['branch', 'country', 'city']));
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

        return (new BranchEmployeeResource($branchEmployee))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(BranchEmployee $branchEmployee)
    {
        abort_if(Gate::denies('branch_employee_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $branchEmployee->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
