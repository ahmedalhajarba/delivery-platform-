<?php

namespace App\Http\Requests;

use App\Models\SubscriptionsPlan;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroySubscriptionsPlanRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('subscriptions_plan_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:subscriptions_plans,id',
        ];
    }
}
