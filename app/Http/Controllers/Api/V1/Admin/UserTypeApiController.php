<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserTypeRequest;
use App\Http\Requests\UpdateUserTypeRequest;
use App\Http\Resources\Admin\UserTypeResource;
use App\Models\UserType;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserTypeApiController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('user_type_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new UserTypeResource(UserType::all());
    }

    public function store(StoreUserTypeRequest $request)
    {
        $userType = UserType::create($request->all());

        return (new UserTypeResource($userType))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(UserType $userType)
    {
        abort_if(Gate::denies('user_type_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new UserTypeResource($userType);
    }

    public function update(UpdateUserTypeRequest $request, UserType $userType)
    {
        $userType->update($request->all());

        return (new UserTypeResource($userType))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(UserType $userType)
    {
        abort_if(Gate::denies('user_type_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $userType->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
