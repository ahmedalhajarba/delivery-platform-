<?php

namespace App\Http\Requests;

use App\Models\Order;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateOrderRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('order_edit');
    }

    public function rules()
    {
        return [
            'shipment_type' => [
                'required',
            ],
            'packages_count' => [
                'nullable',
                'integer',
                'min:-2147483648',
                'max:2147483647',

            ],
            'package_weight' => [
                'numeric',
                // 'digits_between:2,5',

            ],
            'actual_weight' => [
                'numeric',
                // 'digits_between:2,8',
                'nullable',
            ],
            'length' => [
                'numeric',
                'min:0',
                'nullable',
            ],
            'width' => [
                'numeric',
                'min:0',
                'nullable',
            ],
            'height' => [
                'numeric',
                'min:0',
                'nullable',
            ],
            'reference_number' => [
                'string',
                'nullable',
            ],
            'actual_weight' => [
                'digits_between:2,8',
                'nullable',
            ],
            'approved_weight'=> [
                'digits_between:2,8',
                'nullable',
            ],
            'fee_weight'=> [
                'numeric',
                'digits_between:2,8',
                'nullable',
            ],

        ];
    }
}