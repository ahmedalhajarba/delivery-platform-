<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyRoleRequest;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\PermissionMatrixService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RolesController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('role_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $roles = Role::with(['permissions'])->get();

        return view('admin.roles.index', compact('roles'));
    }

    public function create()
    {
        abort_if(Gate::denies('role_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $permissions = Permission::all()->pluck('title', 'id');

        $matrixPayload = app(PermissionMatrixService::class)->build(Permission::with('section')->get());

        return view('admin.roles.create', array_merge(compact('permissions'), $matrixPayload));
    }

    public function store(StoreRoleRequest $request)
    {
        $payload = $request->all();
        $title = trim((string) ($payload['title'] ?? ''));

        $payload['title'] = $title;
        if (!isset($payload['name']) || blank($payload['name'])) {
            $payload['name'] = $title;
        }

        if (!isset($payload['slug']) || blank($payload['slug'])) {
            $payload['slug'] = Str::slug($title, '_');
        }

        $role = Role::create($payload);
        $role->permissions()->sync($request->input('permissions', []));

        return redirect()->route('admin.roles.index');
    }

    public function edit(Role $role)
    {
        abort_if(Gate::denies('role_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $permissions = Permission::all()->pluck('title', 'id');

        $matrixPayload = app(PermissionMatrixService::class)->build(Permission::with('section')->get());

        $role->load('permissions');

        return view('admin.roles.edit', array_merge(compact('permissions', 'role'), $matrixPayload));
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        $payload = $request->all();
        $title = trim((string) ($payload['title'] ?? $role->title ?? ''));

        $payload['title'] = $title;
        if (!isset($payload['name']) || blank($payload['name'])) {
            $payload['name'] = $title;
        }

        if (!isset($payload['slug']) || blank($payload['slug'])) {
            $payload['slug'] = Str::slug($title, '_');
        }

        $role->update($payload);
        $role->permissions()->sync($request->input('permissions', []));

        return redirect()->route('admin.roles.index');
    }

    public function show(Role $role)
    {
        abort_if(Gate::denies('role_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $role->load('permissions');

        return view('admin.roles.show', compact('role'));
    }

    public function destroy(Role $role)
    {
        abort_if(Gate::denies('role_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $role->delete();

        return back();
    }

    public function massDestroy(MassDestroyRoleRequest $request)
    {
        Role::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
