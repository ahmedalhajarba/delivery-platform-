<?php

namespace App\Http\Requests;

use App\Models\CategoriesFeature;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyCategoriesFeatureRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('categories_feature_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:categories_features,id',
        ];
    }
}
