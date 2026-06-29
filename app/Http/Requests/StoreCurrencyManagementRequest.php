<?php

namespace App\Http\Requests;

use App\Models\CurrencyManagement;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreCurrencyManagementRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('currency_management_create');
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
            'symbol' => [
                'string',
                'required',
            ],
            'status' => [
                'required',
            ],
        ];
    }
}
