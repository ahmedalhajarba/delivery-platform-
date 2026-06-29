<?php

namespace App\Http\Requests;

use App\Models\CompanyType;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreCompanyTypeRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('company_type_create');
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
        ];
    }
}
