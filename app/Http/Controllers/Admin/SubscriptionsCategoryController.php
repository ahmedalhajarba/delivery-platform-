<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\MassDestroySubscriptionsCategoryRequest;
use App\Http\Requests\StoreSubscriptionsCategoryRequest;
use App\Http\Requests\UpdateSubscriptionsCategoryRequest;
use App\Models\SubscriptionsCategory;
use Gate;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionsCategoryController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        abort_if(Gate::denies('subscriptions_category_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $subscriptionsCategories = SubscriptionsCategory::with(['media'])->get();

        return view('admin.subscriptionsCategories.index', compact('subscriptionsCategories'));
    }

    public function create()
    {
        abort_if(Gate::denies('subscriptions_category_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.subscriptionsCategories.create');
    }

    public function store(StoreSubscriptionsCategoryRequest $request)
    {
        $subscriptionsCategory = SubscriptionsCategory::create($request->all());

        if ($request->input('image', false)) {
            $subscriptionsCategory->addMedia(storage_path('tmp/uploads/' . basename($request->input('image'))))->toMediaCollection('image');
        }

        if ($media = $request->input('ck-media', false)) {
            Media::whereIn('id', $media)->update(['model_id' => $subscriptionsCategory->id]);
        }

        return redirect()->route('admin.subscriptions-categories.index');
    }

    public function edit(SubscriptionsCategory $subscriptionsCategory)
    {
        abort_if(Gate::denies('subscriptions_category_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.subscriptionsCategories.edit', compact('subscriptionsCategory'));
    }

    public function update(UpdateSubscriptionsCategoryRequest $request, SubscriptionsCategory $subscriptionsCategory)
    {
        $subscriptionsCategory->update($request->all());

        if ($request->input('image', false)) {
            if (!$subscriptionsCategory->image || $request->input('image') !== $subscriptionsCategory->image->file_name) {
                if ($subscriptionsCategory->image) {
                    $subscriptionsCategory->image->delete();
                }
                $subscriptionsCategory->addMedia(storage_path('tmp/uploads/' . basename($request->input('image'))))->toMediaCollection('image');
            }
        } elseif ($subscriptionsCategory->image) {
            $subscriptionsCategory->image->delete();
        }

        return redirect()->route('admin.subscriptions-categories.index');
    }

    public function show(SubscriptionsCategory $subscriptionsCategory)
    {
        abort_if(Gate::denies('subscriptions_category_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $subscriptionsCategory->load('categorySubscriptionsPlans');

        return view('admin.subscriptionsCategories.show', compact('subscriptionsCategory'));
    }

    public function destroy(SubscriptionsCategory $subscriptionsCategory)
    {
        abort_if(Gate::denies('subscriptions_category_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $subscriptionsCategory->delete();

        return back();
    }

    public function massDestroy(MassDestroySubscriptionsCategoryRequest $request)
    {
        SubscriptionsCategory::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function storeCKEditorImages(Request $request)
    {
        abort_if(Gate::denies('subscriptions_category_create') && Gate::denies('subscriptions_category_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $model         = new SubscriptionsCategory();
        $model->id     = $request->input('crud_id', 0);
        $model->exists = true;
        $media         = $model->addMediaFromRequest('upload')->toMediaCollection('ck-media');

        return response()->json(['id' => $media->id, 'url' => $media->getUrl()], Response::HTTP_CREATED);
    }
}
