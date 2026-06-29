<?php

namespace App\Http\Requests;

use App\Models\FeaturesSubscribe;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyFeaturesSubscribeRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('features_subscribe_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:features_subscribes,id',
        ];
    }
}
