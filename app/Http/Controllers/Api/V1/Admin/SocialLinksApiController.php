<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSocialLinkRequest;
use App\Http\Requests\UpdateSocialLinkRequest;
use App\Http\Resources\Admin\SocialLinkResource;
use App\Models\SocialLink;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SocialLinksApiController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('social_link_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new SocialLinkResource(SocialLink::all());
    }

    public function store(StoreSocialLinkRequest $request)
    {
        $socialLink = SocialLink::create($request->all());

        return (new SocialLinkResource($socialLink))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(SocialLink $socialLink)
    {
        abort_if(Gate::denies('social_link_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return new SocialLinkResource($socialLink);
    }

    public function update(UpdateSocialLinkRequest $request, SocialLink $socialLink)
    {
        $socialLink->update($request->all());

        return (new SocialLinkResource($socialLink))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    public function destroy(SocialLink $socialLink)
    {
        abort_if(Gate::denies('social_link_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $socialLink->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
