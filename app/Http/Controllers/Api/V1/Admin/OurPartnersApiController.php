<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\StoreOurPartnerRequest;
use App\Http\Requests\UpdateOurPartnerRequest;
use App\Http\Resources\Admin\OurPartnerResource;
use App\Models\OurPartner;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OurPartnersApiController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        abort_if(Gate::denies('our_partner_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new OurPartnerResource(OurPartner::with(['partner_category'])->get());
    }

    public function store(StoreOurPartnerRequest $request)
    {
        $ourPartner = OurPartner::create($request->all());

        if ($request->input('logo', false)) {
            $ourPartner->addMedia(storage_path('tmp/uploads/' . basename($request->input('logo'))))->toMediaCollection('logo');
        }

        return (new OurPartnerResource($ourPartner))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(OurPartner $ourPartner)
    {
        abort_if(Gate::denies('our_partner_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new OurPartnerResource($ourPartner->load(['partner_category']));
    }

    public function update(UpdateOurPartnerRequest $request, OurPartner $ourPartner)
    {
        $ourPartner->update($request->all());

        if ($request->input('logo', false)) {
            if (!$ourPartner->logo || $request->input('logo') !== $ourPartner->logo->file_name) {
                if ($ourPartner->logo) {
                    $ourPartner->logo->delete();
                }
                $ourPartner->addMedia(storage_path('tmp/uploads/' . basename($request->input('logo'))))->toMediaCollection('logo');
            }
        } elseif ($ourPartner->logo) {
            $ourPartner->logo->delete();
        }

        return (new OurPartnerResource($ourPartner))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(OurPartner $ourPartner)
    {
        abort_if(Gate::denies('our_partner_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $ourPartner->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
