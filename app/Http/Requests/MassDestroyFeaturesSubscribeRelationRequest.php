<?php

namespace App\Http\Requests;

use App\Models\FeaturesSubscribeRelation;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyFeaturesSubscribeRelationRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('features_subscribe_relation_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:features_subscribe_relations,id',
        ];
    }
}
