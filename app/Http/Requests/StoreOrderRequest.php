<?php

namespace App\Http\Requests;

use App\Models\Order;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreOrderRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('order_create');
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
                'digits_between:2,5',
            ],
            'actual_weight' => [
                'numeric',
                'min:0',
                'max:10',
            ],
            'length' => [
                'numeric',
                'min:0',
            ],
            'width' => [
                'numeric',
                'min:0',
            ],
            'height' => [
                'numeric',
                'min:0',
            ],
            'reference_number' => [
                'string',
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