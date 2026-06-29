<?php

namespace App\Http\Requests;

use App\Models\ConnectYourStoreWithUs;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyConnectYourStoreWithUsRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('connect_your_store_with_us_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:connect_your_store_withuses,id',
        ];
    }
}
