<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyFeaturesSubscribeRequest;
use App\Http\Requests\StoreFeaturesSubscribeRequest;
use App\Http\Requests\UpdateFeaturesSubscribeRequest;
use App\Models\FeaturesSubscribe;
use App\Models\SubscriptionsPlan;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FeaturesSubscribeController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('features_subscribe_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $featuresSubscribes = FeaturesSubscribe::with(['subscription_plan'])->get();

        return view('admin.featuresSubscribes.index', compact('featuresSubscribes'));
    }

    public function create()
    {
        abort_if(Gate::denies('features_subscribe_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

//        $subscription_plans = SubscriptionsPlan::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');
        $subscription_plans = SubscriptionsPlan::with('subscription_period')->get();

        return view('admin.featuresSubscribes.create', compact('subscription_plans'));
    }

    public function store(StoreFeaturesSubscribeRequest $request)
    {
        $featuresSubscribe = FeaturesSubscribe::create($request->all());

        return redirect()->route('admin.features-subscribes.index');
    }

    public function edit(FeaturesSubscribe $featuresSubscribe)
    {
        abort_if(Gate::denies('features_subscribe_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $subscription_plans = SubscriptionsPlan::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $featuresSubscribe->load('subscription_plan');

        return view('admin.featuresSubscribes.edit', compact('subscription_plans', 'featuresSubscribe'));
    }

    public function update(UpdateFeaturesSubscribeRequest $request, FeaturesSubscribe $featuresSubscribe)
    {
        $featuresSubscribe->update($request->all());

        return redirect()->route('admin.features-subscribes.index');
    }

    public function show(FeaturesSubscribe $featuresSubscribe)
    {
        abort_if(Gate::denies('features_subscribe_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $featuresSubscribe->load('subscription_plan', 'featureFeaturesSubscribeRelations');

        return view('admin.featuresSubscribes.show', compact('featuresSubscribe'));
    }

    public function destroy(FeaturesSubscribe $featuresSubscribe)
    {
        abort_if(Gate::denies('features_subscribe_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $featuresSubscribe->delete();

        return back();
    }

    public function massDestroy(MassDestroyFeaturesSubscribeRequest $request)
    {
        FeaturesSubscribe::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
