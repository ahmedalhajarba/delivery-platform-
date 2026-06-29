<?php

namespace App\Http\Requests;

use App\Models\FinancialOfficer;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreFinancialOfficerRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('financial_officer_create');
    }

    public function rules()
    {
        return [
            'company_id' => [
                'required',
                'integer',
            ],
            'name' => [
                'string',
                'required',
            ],
            'email' => [
                'required',
            ],
            'mobile' => [
                'string',
                'required',
            ],
        ];
    }
}
