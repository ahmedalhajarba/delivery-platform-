<?php

namespace App\Http\Requests;

use App\Models\CategoriesFeature;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreCategoriesFeatureRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('categories_feature_create');
    }

    public function rules()
    {
        return [
            'title_ar' => [
                'string',
                'required',
            ],
            'title_en' => [
                'string',
                'required',
            ],
        ];
    }
}
