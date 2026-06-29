<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyUserTypeRequest;
use App\Http\Requests\StoreUserTypeRequest;
use App\Http\Requests\UpdateUserTypeRequest;
use App\Models\Role;
use App\Models\UserType;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserTypeController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('user_type_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $userTypes = UserType::withCount('users')->with('roles')->get();
        $stats = [
            'total'         => $userTypes->count(),
            'with_roles'    => $userTypes->filter(fn($t) => $t->roles->count() > 0)->count(),
            'total_users'   => $userTypes->sum('users_count'),
        ];

        return view('admin.userTypes.index', compact('userTypes', 'stats'));
    }

    public function create()
    {
        abort_if(Gate::denies('user_type_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $roles = Role::orderBy('title')->get();

        return view('admin.userTypes.create', compact('roles'));
    }

    public function store(StoreUserTypeRequest $request)
    {
        $userType = UserType::create($request->only(['title_ar', 'title_en', 'icon', 'color', 'description']));

        if ($request->has('roles')) {
            $userType->roles()->sync($request->input('roles', []));
        }

        return redirect()->route('admin.user-types.index');
    }

    public function edit(UserType $userType)
    {
        abort_if(Gate::denies('user_type_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $roles = Role::orderBy('title')->get();
        $userType->load('roles');

        return view('admin.userTypes.edit', compact('userType', 'roles'));
    }

    public function update(UpdateUserTypeRequest $request, UserType $userType)
    {
        $userType->update($request->only(['title_ar', 'title_en', 'icon', 'color', 'description']));

        $userType->roles()->sync($request->input('roles', []));

        return redirect()->route('admin.user-types.index');
    }

    public function show(UserType $userType)
    {
        abort_if(Gate::denies('user_type_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $userType->load('roles', 'users');

        return view('admin.userTypes.show', compact('userType'));
    }

    public function destroy(UserType $userType)
    {
        abort_if(Gate::denies('user_type_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $userType->delete();

        return back();
    }

    public function massDestroy(MassDestroyUserTypeRequest $request)
    {
        UserType::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function getRoles(UserType $userType)
    {
        $roleIds = $userType->roles()->pluck('roles.id');
        return response()->json(['role_ids' => $roleIds]);
    }
}

