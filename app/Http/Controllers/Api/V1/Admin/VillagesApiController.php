<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVillageRequest;
use App\Http\Requests\UpdateVillageRequest;
use App\Http\Resources\Admin\VillageResource;
use App\Models\Village;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VillagesApiController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('village_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new VillageResource(Village::with(['governorate'])->get());
    }

    public function store(StoreVillageRequest $request)
    {
        $village = Village::create($request->all());

        return (new VillageResource($village))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Village $village)
    {
        abort_if(Gate::denies('village_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new VillageResource($village->load(['governorate']));
    }

    public function update(UpdateVillageRequest $request, Village $village)
    {
        $village->update($request->all());

        return (new VillageResource($village))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(Village $village)
    {
        abort_if(Gate::denies('village_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $village->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
