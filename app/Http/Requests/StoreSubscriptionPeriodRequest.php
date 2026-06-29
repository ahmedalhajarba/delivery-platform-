<?php

namespace App\Http\Requests;

use App\Models\SubscriptionPeriod;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreSubscriptionPeriodRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('subscription_period_create');
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
            'period' => [
                'nullable',
                'integer',
                'min:-2147483648',
                'max:2147483647',
            ],
            'status' => [
                'required',
            ],
        ];
    }
}
