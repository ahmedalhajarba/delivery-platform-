<?php

namespace App\Http\Requests;

use App\Models\UserSubscription;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateUserSubscriptionRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('user_subscription_edit');
    }

    public function rules()
    {
        return [
            'user_id' => [
                'required',
                'integer',
            ],
            'subscription_id' => [
                'required',
                'integer',
            ],
            'monthly_price' => [
                'required',
            ],
            'order_limit' => [
                'nullable',
                'integer',
                'min:-2147483648',
                'max:2147483647',
            ],
            'start_date' => [
                'required',
                'date_format:' . config('panel.date_format'),
            ],
            'end_date' => [
                'required',
                'date_format:' . config('panel.date_format'),
            ],
            'status' => [
                'required',
            ],
        ];
    }
}
