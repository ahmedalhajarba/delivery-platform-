<?php

namespace App\Http\Requests;

use App\Models\SubscriptionsPlan;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateSubscriptionsPlanRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('subscriptions_plan_edit');
    }

    public function rules()
    {
        return [
            'category_id' => [
                'required',
                'integer',
            ],
            'title_ar' => [
                'string',
                'required',
            ],
            'title_en' => [
                'string',
                'nullable',
            ],
            'orders_count' => [
                'required',
                'integer',
                'min:-2147483648',
                'max:2147483647',
            ],
            'order_price' => [
                'required',
            ],
            'subscription_period_id' => [
                'required',
                'integer',
            ],
            'status' => [
                'required',
            ],
            'store_type' => [
                'required',
            ],
            'business_type' => [
                'required',
            ],
        ];
    }
}
