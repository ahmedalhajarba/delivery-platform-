<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroySubscriptionPeriodRequest;
use App\Http\Requests\StoreSubscriptionPeriodRequest;
use App\Http\Requests\UpdateSubscriptionPeriodRequest;
use App\Models\SubscriptionPeriod;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionPeriodsController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('subscription_period_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $subscriptionPeriods = SubscriptionPeriod::all();

        return view('admin.subscriptionPeriods.index', compact('subscriptionPeriods'));
    }

    public function create()
    {
        abort_if(Gate::denies('subscription_period_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.subscriptionPeriods.create');
    }

    public function store(StoreSubscriptionPeriodRequest $request)
    {
        $subscriptionPeriod = SubscriptionPeriod::create($request->all());

        return redirect()->route('admin.subscription-periods.index');
    }

    public function edit(SubscriptionPeriod $subscriptionPeriod)
    {
        abort_if(Gate::denies('subscription_period_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.subscriptionPeriods.edit', compact('subscriptionPeriod'));
    }

    public function update(UpdateSubscriptionPeriodRequest $request, SubscriptionPeriod $subscriptionPeriod)
    {
        $subscriptionPeriod->update($request->all());

        return redirect()->route('admin.subscription-periods.index');
    }

    public function show(SubscriptionPeriod $subscriptionPeriod)
    {
        abort_if(Gate::denies('subscription_period_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $subscriptionPeriod->load('subscriptionPeriodSubscriptionsPlans');

        return view('admin.subscriptionPeriods.show', compact('subscriptionPeriod'));
    }

    public function destroy(SubscriptionPeriod $subscriptionPeriod)
    {
        abort_if(Gate::denies('subscription_period_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $subscriptionPeriod->delete();

        return back();
    }

    public function massDestroy(MassDestroySubscriptionPeriodRequest $request)
    {
        SubscriptionPeriod::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
