<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\MassDestroySubscriptionsPlanRequest;
use App\Http\Requests\StoreSubscriptionsPlanRequest;
use App\Http\Requests\UpdateSubscriptionsPlanRequest;
use App\Models\SubscriptionPeriod;
use App\Models\SubscriptionsCategory;
use App\Models\SubscriptionsPlan;
use Gate;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionsPlansController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        abort_if(Gate::denies('subscriptions_plan_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $subscriptionsPlans = SubscriptionsPlan::with(['category', 'subscription_period', 'media'])->get();

        return view('admin.subscriptionsPlans.index', compact('subscriptionsPlans'));
    }

    public function create()
    {
        abort_if(Gate::denies('subscriptions_plan_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $categories = SubscriptionsCategory::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $subscription_periods = SubscriptionPeriod::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.subscriptionsPlans.create', compact('categories', 'subscription_periods'));
    }

    public function store(StoreSubscriptionsPlanRequest $request)
    {
        $subscriptionsPlan = SubscriptionsPlan::create($request->all());

        if ($request->input('image', false)) {
            $subscriptionsPlan->addMedia(storage_path('tmp/uploads/' . basename($request->input('image'))))->toMediaCollection('image');
        }

        if ($media = $request->input('ck-media', false)) {
            Media::whereIn('id', $media)->update(['model_id' => $subscriptionsPlan->id]);
        }

        return redirect()->route('admin.subscriptions-plans.index');
    }

    public function edit(SubscriptionsPlan $subscriptionsPlan)
    {
        abort_if(Gate::denies('subscriptions_plan_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $categories = SubscriptionsCategory::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $subscription_periods = SubscriptionPeriod::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $subscriptionsPlan->load('category', 'subscription_period');

        return view('admin.subscriptionsPlans.edit', compact('categories', 'subscription_periods', 'subscriptionsPlan'));
    }

    public function update(UpdateSubscriptionsPlanRequest $request, SubscriptionsPlan $subscriptionsPlan)
    {
        $subscriptionsPlan->update($request->all());

        if ($request->input('image', false)) {
            if (!$subscriptionsPlan->image || $request->input('image') !== $subscriptionsPlan->image->file_name) {
                if ($subscriptionsPlan->image) {
                    $subscriptionsPlan->image->delete();
                }
                $subscriptionsPlan->addMedia(storage_path('tmp/uploads/' . basename($request->input('image'))))->toMediaCollection('image');
            }
        } elseif ($subscriptionsPlan->image) {
            $subscriptionsPlan->image->delete();
        }

        return redirect()->route('admin.subscriptions-plans.index');
    }

    public function show(SubscriptionsPlan $subscriptionsPlan)
    {
        abort_if(Gate::denies('subscriptions_plan_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $subscriptionsPlan->load('category', 'subscription_period', 'subscriptionUserSubscriptions', 'subscriptionPlanFeaturesSubscribes');

        return view('admin.subscriptionsPlans.show', compact('subscriptionsPlan'));
    }

    public function destroy(SubscriptionsPlan $subscriptionsPlan)
    {
        abort_if(Gate::denies('subscriptions_plan_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $subscriptionsPlan->delete();

        return back();
    }

    public function massDestroy(MassDestroySubscriptionsPlanRequest $request)
    {
        SubscriptionsPlan::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function storeCKEditorImages(Request $request)
    {
        abort_if(Gate::denies('subscriptions_plan_create') && Gate::denies('subscriptions_plan_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $model         = new SubscriptionsPlan();
        $model->id     = $request->input('crud_id', 0);
        $model->exists = true;
        $media         = $model->addMediaFromRequest('upload')->toMediaCollection('ck-media');

        return response()->json(['id' => $media->id, 'url' => $media->getUrl()], Response::HTTP_CREATED);
    }
}
