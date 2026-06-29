<?php

namespace App\Http\Requests;

use App\Models\Address;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreAddressRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('address_create');
    }

     public function rules()
    {
        return [
            'name' => [
                'string',
                'required',
            ],
            'user_id' => [
                'integer',
            ],
            'mobile' => [
                'string',
                'required',
            ],
            's_mobile' => [
                'string',
                'nullable',
            ],
            'country_id' => [
                'required',
                'integer',
            ],
            'governorate_id' => [
                'required',
                'integer',
            ],
            'city_id' => [
                'required',
                'integer',
            ],
            'neighborhood' => [
                'required',

            ],
            'type' => [
                'required',
            ],
        ];
    }
}