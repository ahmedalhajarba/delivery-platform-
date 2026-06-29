<?php

namespace App\Http\Requests;

use App\Models\FeaturesSubscribe;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreFeaturesSubscribeRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('features_subscribe_create');
    }

    public function rules()
    {
        return [
            'subscription_plan_id' => [
                'required',
                'integer',
            ],
            'type' => [
                'required',
            ],
            'title_ar' => [
                'string',
                'required',
            ],
            'title_en' => [
                'string',
                'nullable',
            ],
            'status' => [
                'required',
            ],
        ];
    }
}
