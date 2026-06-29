<?php

namespace App\Http\Requests;

use App\Models\SubscriptionsCategory;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateSubscriptionsCategoryRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('subscriptions_category_edit');
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
                'nullable',
            ],
            'status' => [
                'required',
            ],
        ];
    }
}
