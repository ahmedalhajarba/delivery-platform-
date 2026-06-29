<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyCounterAreaRequest;
use App\Http\Requests\StoreCounterAreaRequest;
use App\Http\Requests\UpdateCounterAreaRequest;
use App\Models\CounterArea;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CounterAreaController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('counter_area_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $counterAreas = CounterArea::all();

        return view('admin.counterAreas.index', compact('counterAreas'));
    }

    public function create()
    {
        abort_if(Gate::denies('counter_area_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.counterAreas.create');
    }

    public function store(StoreCounterAreaRequest $request)
    {
        $counterArea = CounterArea::create($request->all());

        return redirect()->route('admin.counter-areas.index');
    }

    public function edit(CounterArea $counterArea)
    {
        abort_if(Gate::denies('counter_area_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.counterAreas.edit', compact('counterArea'));
    }

    public function update(UpdateCounterAreaRequest $request, CounterArea $counterArea)
    {
        $counterArea->update($request->all());

        return redirect()->route('admin.counter-areas.index');
    }

    public function show(CounterArea $counterArea)
    {
        abort_if(Gate::denies('counter_area_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.counterAreas.show', compact('counterArea'));
    }

    public function destroy(CounterArea $counterArea)
    {
        abort_if(Gate::denies('counter_area_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $counterArea->delete();

        return back();
    }

    public function massDestroy(MassDestroyCounterAreaRequest $request)
    {
        CounterArea::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
