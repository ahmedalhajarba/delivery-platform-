<?php

namespace App\Http\Requests;

use App\Models\FeaturesSubscribeRelation;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreFeaturesSubscribeRelationRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('features_subscribe_relation_create');
    }

    public function rules()
    {
        return [];
    }
}
