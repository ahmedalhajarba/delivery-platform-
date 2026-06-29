<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNeighborhoodRequest;
use App\Http\Requests\UpdateNeighborhoodRequest;
use App\Http\Resources\Admin\NeighborhoodResource;
use App\Models\Neighborhood;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NeighborhoodApiController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('neighborhood_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new NeighborhoodResource(Neighborhood::with(['city'])->get());
    }

    public function store(StoreNeighborhoodRequest $request)
    {
        $neighborhood = Neighborhood::create($request->all());

        return (new NeighborhoodResource($neighborhood))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Neighborhood $neighborhood)
    {
        abort_if(Gate::denies('neighborhood_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new NeighborhoodResource($neighborhood->load(['city']));
    }

    public function update(UpdateNeighborhoodRequest $request, Neighborhood $neighborhood)
    {
        $neighborhood->update($request->all());

        return (new NeighborhoodResource($neighborhood))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Neighborhood $neighborhood)
    {
        abort_if(Gate::denies('neighborhood_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $neighborhood->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
