<?php

namespace App\Http\Requests;

use App\Models\OrderSetting;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreOrderSettingRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('order_setting_create');
    }

    public function rules()
    {
        return [
            'insurance_rate' => [
                'required',
                'integer',
                'min:-2147483648',
                'max:2147483647',
            ],
            'divided_number' => [
                'required',
                'integer',
                'min:-2147483648',
                'max:2147483647',
            ],
            'print_copies' => [
                'required',
                'integer',
                'min:-2147483648',
                'max:2147483647',
            ],
            'allowed_weight' => [
                'nullable',
                'integer',
                'min:-2147483648',
                'max:2147483647',
            ],
            'sender' => [
                'required',
            ],
            'recipient' => [
                'required',
            ],
            'shipment_type' => [
                'required',
            ],
            'package_content' => [
                'required',
            ],
            'packages_count' => [
                'required',
            ],
            'package_weight' => [
                'required',
            ],
            'actual_weight' => [
                'required',
            ],
            'length' => [
                'required',
            ],
            'width' => [
                'required',
            ],
            'height' => [
                'required',
            ],
            'stated_value' => [
                'required',
            ],
            'reference_number' => [
                'required',
            ],
            'note' => [
                'required',
            ],
        ];
    }
}
