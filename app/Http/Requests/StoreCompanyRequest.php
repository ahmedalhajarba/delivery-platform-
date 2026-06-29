<?php

namespace App\Http\Requests;

use App\Models\Company;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreCompanyRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('company_create');
    }

    public function rules()
    {
        return [
            'name_ar' => [
                'string',
                'required',
            ],
            'name_en' => [
                'string',
                'required',
            ],
            'trade_name_ar' => [
                'string',
                'nullable',
            ],
            'trade_name_en' => [
                'string',
                'nullable',
            ],
            'have_en_account' => [
                'required',
            ],
            'country' => [
                'string',
                'nullable',
            ],
            'account_code' => [
                'string',
                'nullable',
            ],
            'account_number' => [
                'string',
                'nullable',
            ],
            'crn' => [
                'string',
                'required',
            ],
            'tax' => [
                'string',
                'required',
            ],
            'city_id' => [
                'required',
                'integer',
            ],
            'street_name' => [
                'string',
                'nullable',
            ],
            'mobile' => [
                'string',
                'nullable',
            ],
            'company_type_id' => [
                'required',
                'integer',
            ],
        ];
    }
}
