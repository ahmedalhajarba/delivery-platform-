<?php

namespace App\Http\Requests;

use App\Models\BranchCategory;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreBranchCategoryRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('branch_category_create');
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
