<?php

namespace App\Http\Requests;

use App\Models\TaxSetting;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreTaxSettingRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('tax_setting_create');
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
            'tax_value' => [
                'string',
                'required',
            ],
            'status' => [
                'required',
            ],
        ];
    }
}
