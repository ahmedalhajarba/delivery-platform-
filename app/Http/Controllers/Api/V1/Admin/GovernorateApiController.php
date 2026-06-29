<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGovernorateRequest;
use App\Http\Requests\UpdateGovernorateRequest;
use App\Http\Resources\Admin\GovernorateResource;
use App\Models\Governorate;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GovernorateApiController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('governorate_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new GovernorateResource(Governorate::with(['region'])->get());
    }

    public function store(StoreGovernorateRequest $request)
    {
        $governorate = Governorate::create($request->all());

        return (new GovernorateResource($governorate))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Governorate $governorate)
    {
        abort_if(Gate::denies('governorate_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new GovernorateResource($governorate->load(['region']));
    }

    public function update(UpdateGovernorateRequest $request, Governorate $governorate)
    {
        $governorate->update($request->all());

        return (new GovernorateResource($governorate))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Governorate $governorate)
    {
        abort_if(Gate::denies('governorate_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $governorate->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
