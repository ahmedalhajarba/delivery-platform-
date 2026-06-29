<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCounterAreaRequest;
use App\Http\Requests\UpdateCounterAreaRequest;
use App\Http\Resources\Admin\CounterAreaResource;
use App\Models\CounterArea;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CounterAreaApiController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('counter_area_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new CounterAreaResource(CounterArea::all());
    }

    public function store(StoreCounterAreaRequest $request)
    {
        $counterArea = CounterArea::create($request->all());

        return (new CounterAreaResource($counterArea))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(CounterArea $counterArea)
    {
        abort_if(Gate::denies('counter_area_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new CounterAreaResource($counterArea);
    }

    public function update(UpdateCounterAreaRequest $request, CounterArea $counterArea)
    {
        $counterArea->update($request->all());

        return (new CounterAreaResource($counterArea))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(CounterArea $counterArea)
    {
        abort_if(Gate::denies('counter_area_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $counterArea->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
