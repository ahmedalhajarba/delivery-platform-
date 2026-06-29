<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyCategoriesFeatureRequest;
use App\Http\Requests\StoreCategoriesFeatureRequest;
use App\Http\Requests\UpdateCategoriesFeatureRequest;
use App\Models\CategoriesFeature;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CategoriesFeaturesController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('categories_feature_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $categoriesFeatures = CategoriesFeature::all();

        return view('admin.categoriesFeatures.index', compact('categoriesFeatures'));
    }

    public function create()
    {
        abort_if(Gate::denies('categories_feature_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.categoriesFeatures.create');
    }

    public function store(StoreCategoriesFeatureRequest $request)
    {
        $categoriesFeature = CategoriesFeature::create($request->all());

        return redirect()->route('admin.categories-features.index');
    }

    public function edit(CategoriesFeature $categoriesFeature)
    {
        abort_if(Gate::denies('categories_feature_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.categoriesFeatures.edit', compact('categoriesFeature'));
    }

    public function update(UpdateCategoriesFeatureRequest $request, CategoriesFeature $categoriesFeature)
    {
        $categoriesFeature->update($request->all());

        return redirect()->route('admin.categories-features.index');
    }

    public function show(CategoriesFeature $categoriesFeature)
    {
        abort_if(Gate::denies('categories_feature_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.categoriesFeatures.show', compact('categoriesFeature'));
    }

    public function destroy(CategoriesFeature $categoriesFeature)
    {
        abort_if(Gate::denies('categories_feature_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $categoriesFeature->delete();

        return back();
    }

    public function massDestroy(MassDestroyCategoriesFeatureRequest $request)
    {
        CategoriesFeature::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
