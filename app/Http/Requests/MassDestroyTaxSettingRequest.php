<?php

namespace App\Http\Requests;

use App\Models\TaxSetting;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyTaxSettingRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('tax_setting_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:tax_settings,id',
        ];
    }
}
