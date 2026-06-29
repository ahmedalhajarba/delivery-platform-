<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyHowCanHelpRequest;
use App\Http\Requests\StoreHowCanHelpRequest;
use App\Http\Requests\UpdateHowCanHelpRequest;
use App\Models\HowCanHelp;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

class HowCanHelpController extends Controller
{
    public function index(Request $request)
    {
        abort_if(Gate::denies('how_can_help_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->ajax()) {
            $query = HowCanHelp::query()->select(sprintf('%s.*', (new HowCanHelp())->table));
            $table = Datatables::of($query);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $viewGate = 'how_can_help_show';
                $editGate = 'how_can_help_edit';
                $deleteGate = 'how_can_help_delete';
                $crudRoutePart = 'how-can-helps';

                return view('partials.datatablesActions', compact(
                'viewGate',
                'editGate',
                'deleteGate',
                'crudRoutePart',
                'row'
            ));
            });

            $table->editColumn('id', function ($row) {
                return $row->id ? $row->id : '';
            });
            $table->editColumn('icon', function ($row) {
                return $row->icon ? HowCanHelp::ICON_SELECT[$row->icon] : '';
            });
            $table->editColumn('title_en', function ($row) {
                return $row->title_en ? $row->title_en : '';
            });
            $table->editColumn('title_ar', function ($row) {
                return $row->title_ar ? $row->title_ar : '';
            });
            $table->editColumn('description_en', function ($row) {
                return $row->description_en ? $row->description_en : '';
            });
            $table->editColumn('description_ar', function ($row) {
                return $row->description_ar ? $row->description_ar : '';
            });

            $table->rawColumns(['actions', 'placeholder']);

            return $table->make(true);
        }

        return view('admin.howCanHelps.index');
    }

    public function create()
    {
        abort_if(Gate::denies('how_can_help_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.howCanHelps.create');
    }

    public function store(StoreHowCanHelpRequest $request)
    {
        $howCanHelp = HowCanHelp::create($request->all());

        return redirect()->route('admin.how-can-helps.index');
    }

    public function edit(HowCanHelp $howCanHelp)
    {
        abort_if(Gate::denies('how_can_help_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.howCanHelps.edit', compact('howCanHelp'));
    }

    public function update(UpdateHowCanHelpRequest $request, HowCanHelp $howCanHelp)
    {
        $howCanHelp->update($request->all());

        return redirect()->route('admin.how-can-helps.index');
    }

    public function show(HowCanHelp $howCanHelp)
    {
        abort_if(Gate::denies('how_can_help_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.howCanHelps.show', compact('howCanHelp'));
    }

    public function destroy(HowCanHelp $howCanHelp)
    {
        abort_if(Gate::denies('how_can_help_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $howCanHelp->delete();

        return back();
    }

    public function massDestroy(MassDestroyHowCanHelpRequest $request)
    {
        HowCanHelp::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
