<?php

namespace App\Http\Requests;

use App\Models\UserSubscription;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyUserSubscriptionRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('user_subscription_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:user_subscriptions,id',
        ];
    }
}
