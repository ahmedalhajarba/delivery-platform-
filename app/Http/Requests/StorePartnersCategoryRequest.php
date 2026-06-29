<?php

namespace App\Http\Requests;

use App\Models\PartnersCategory;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StorePartnersCategoryRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('partners_category_create');
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
                'nullable',
            ],
        ];
    }
}
