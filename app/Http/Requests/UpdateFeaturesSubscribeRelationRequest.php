<?php

namespace App\Http\Requests;

use App\Models\FeaturesSubscribeRelation;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateFeaturesSubscribeRelationRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('features_subscribe_relation_edit');
    }

    public function rules()
    {
        return [];
    }
}
