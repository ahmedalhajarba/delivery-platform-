<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyFeaturesSubscribeRelationRequest;
use App\Http\Requests\StoreFeaturesSubscribeRelationRequest;
use App\Http\Requests\UpdateFeaturesSubscribeRelationRequest;
use App\Models\FeaturesSubscribe;
use App\Models\FeaturesSubscribeRelation;
use App\Models\SubscriptionsPlan;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FeaturesSubscribeRelationController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('features_subscribe_relation_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $featuresSubscribeRelations = FeaturesSubscribeRelation::with(['feature', 'subscription'])->get();

        return view('admin.featuresSubscribeRelations.index', compact('featuresSubscribeRelations'));
    }

    public function create()
    {
        abort_if(Gate::denies('features_subscribe_relation_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $features = FeaturesSubscribe::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $subscriptions = SubscriptionsPlan::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.featuresSubscribeRelations.create', compact('features', 'subscriptions'));
    }

    public function store(StoreFeaturesSubscribeRelationRequest $request)
    {
        $featuresSubscribeRelation = FeaturesSubscribeRelation::create($request->all());

        return redirect()->route('admin.features-subscribe-relations.index');
    }

    public function edit(FeaturesSubscribeRelation $featuresSubscribeRelation)
    {
        abort_if(Gate::denies('features_subscribe_relation_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $features = FeaturesSubscribe::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $subscriptions = SubscriptionsPlan::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $featuresSubscribeRelation->load('feature', 'subscription');

        return view('admin.featuresSubscribeRelations.edit', compact('features', 'subscriptions', 'featuresSubscribeRelation'));
    }

    public function update(UpdateFeaturesSubscribeRelationRequest $request, FeaturesSubscribeRelation $featuresSubscribeRelation)
    {
        $featuresSubscribeRelation->update($request->all());

        return redirect()->route('admin.features-subscribe-relations.index');
    }

    public function show(FeaturesSubscribeRelation $featuresSubscribeRelation)
    {
        abort_if(Gate::denies('features_subscribe_relation_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $featuresSubscribeRelation->load('feature', 'subscription');

        return view('admin.featuresSubscribeRelations.show', compact('featuresSubscribeRelation'));
    }

    public function destroy(FeaturesSubscribeRelation $featuresSubscribeRelation)
    {
        abort_if(Gate::denies('features_subscribe_relation_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $featuresSubscribeRelation->delete();

        return back();
    }

    public function massDestroy(MassDestroyFeaturesSubscribeRelationRequest $request)
    {
        FeaturesSubscribeRelation::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
