<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\StoreLegalResponsibilityPageRequest;
use App\Http\Requests\UpdateLegalResponsibilityPageRequest;
use App\Http\Resources\Admin\LegalResponsibilityPageResource;
use App\Models\LegalResponsibilityPage;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LegalResponsibilityPagesApiController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        abort_if(Gate::denies('legal_responsibility_page_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new LegalResponsibilityPageResource(LegalResponsibilityPage::all());
    }

    public function store(StoreLegalResponsibilityPageRequest $request)
    {
        $legalResponsibilityPage = LegalResponsibilityPage::create($request->all());

        return (new LegalResponsibilityPageResource($legalResponsibilityPage))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(LegalResponsibilityPage $legalResponsibilityPage)
    {
        abort_if(Gate::denies('legal_responsibility_page_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new LegalResponsibilityPageResource($legalResponsibilityPage);
    }

    public function update(UpdateLegalResponsibilityPageRequest $request, LegalResponsibilityPage $legalResponsibilityPage)
    {
        $legalResponsibilityPage->update($request->all());

        return (new LegalResponsibilityPageResource($legalResponsibilityPage))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(LegalResponsibilityPage $legalResponsibilityPage)
    {
        abort_if(Gate::denies('legal_responsibility_page_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $legalResponsibilityPage->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
