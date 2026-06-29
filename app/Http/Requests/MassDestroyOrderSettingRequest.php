<?php

namespace App\Http\Requests;

use App\Models\OrderSetting;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyOrderSettingRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('order_setting_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:order_settings,id',
        ];
    }
}
