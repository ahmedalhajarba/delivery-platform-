<?php

namespace App\Http\Requests;

use App\Models\CompanyType;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateCompanyTypeRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('company_type_edit');
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
