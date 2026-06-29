<?php

namespace App\Http\Requests;

use App\Models\TermsCondition;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreTermsConditionRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('terms_condition_create');
    }

    public function rules()
    {
        return [
            'title_ar' => [
                'string',
                'min:2',
                'required',
            ],
            'title_en' => [
                'string',
                'required',
            ],
            'text_ar' => [
                'required',
            ],
            'text_en' => [
                'required',
            ],
        ];
    }
}
