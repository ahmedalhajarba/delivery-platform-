<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\MassDestroyLegalResponsibilityPageRequest;
use App\Http\Requests\StoreLegalResponsibilityPageRequest;
use App\Http\Requests\UpdateLegalResponsibilityPageRequest;
use App\Models\LegalResponsibilityPage;
use Gate;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

class LegalResponsibilityPagesController extends Controller
{
    use MediaUploadingTrait;

    // public function index(Request $request)
    // {
    //     abort_if(Gate::denies('legal_responsibility_page_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

    //     if ($request->ajax()) {
    //         $query = LegalResponsibilityPage::query()->select(sprintf('%s.*', (new LegalResponsibilityPage())->table));
    //         $table = Datatables::of($query);

    //         $table->addColumn('placeholder', '&nbsp;');
    //         $table->addColumn('actions', '&nbsp;');

    //         $table->editColumn('actions', function ($row) {
    //             $viewGate = 'legal_responsibility_page_show';
    //             $editGate = 'legal_responsibility_page_edit';
    //             $deleteGate = 'legal_responsibility_page_delete';
    //             $crudRoutePart = 'legal-responsibility-pages';

    //             return view('partials.datatablesActions', compact(
    //             'viewGate',
    //             'editGate',
    //             'deleteGate',
    //             'crudRoutePart',
    //             'row'
    //         ));
    //         });

    //         $table->editColumn('id', function ($row) {
    //             return $row->id ? $row->id : '';
    //         });
    //         $table->editColumn('title_ar', function ($row) {
    //             return $row->title_ar ? $row->title_ar : '';
    //         });
    //         $table->editColumn('title_en', function ($row) {
    //             return $row->title_en ? $row->title_en : '';
    //         });

    //         $table->rawColumns(['actions', 'placeholder']);

    //         return $table->make(true);
    //     }

    //     return view('admin.legalResponsibilityPages.index');
    // }
    
    public function index(){
        
         $legalResponsibilityPage = LegalResponsibilityPage::where('id',1)->first();
        abort_if(Gate::denies('legal_responsibility_page_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.legalResponsibilityPages.edit', compact('legalResponsibilityPage'));
    }

    public function create()
    {
        abort_if(Gate::denies('legal_responsibility_page_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.legalResponsibilityPages.create');
    }

    public function store(StoreLegalResponsibilityPageRequest $request)
    {
        $legalResponsibilityPage = LegalResponsibilityPage::create($request->all());

        if ($media = $request->input('ck-media', false)) {
            Media::whereIn('id', $media)->update(['model_id' => $legalResponsibilityPage->id]);
        }

        return redirect()->route('admin.legal-responsibility-pages.index');
    }

    public function edit(LegalResponsibilityPage $legalResponsibilityPage)
    {
        abort_if(Gate::denies('legal_responsibility_page_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.legalResponsibilityPages.edit', compact('legalResponsibilityPage'));
    }

    public function update(UpdateLegalResponsibilityPageRequest $request, LegalResponsibilityPage $legalResponsibilityPage)
    {
        $legalResponsibilityPage->update($request->all());

        return redirect()->route('admin.legal-responsibility-pages.index');
    }

    public function show(LegalResponsibilityPage $legalResponsibilityPage)
    {
        abort_if(Gate::denies('legal_responsibility_page_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.legalResponsibilityPages.show', compact('legalResponsibilityPage'));
    }

    public function destroy(LegalResponsibilityPage $legalResponsibilityPage)
    {
        abort_if(Gate::denies('legal_responsibility_page_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $legalResponsibilityPage->delete();

        return back();
    }

    public function massDestroy(MassDestroyLegalResponsibilityPageRequest $request)
    {
        LegalResponsibilityPage::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function storeCKEditorImages(Request $request)
    {
        abort_if(Gate::denies('legal_responsibility_page_create') && Gate::denies('legal_responsibility_page_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $model         = new LegalResponsibilityPage();
        $model->id     = $request->input('crud_id', 0);
        $model->exists = true;
        $media         = $model->addMediaFromRequest('upload')->toMediaCollection('ck-media');

        return response()->json(['id' => $media->id, 'url' => $media->getUrl()], Response::HTTP_CREATED);
    }
}
