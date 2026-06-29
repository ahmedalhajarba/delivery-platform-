<?php

namespace App\Http\Requests;

use App\Models\Branch;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreBranchRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('branch_create');
    }

    public function rules()
    {
        return [
            'number' => [
                'string',
                'nullable',
            ],
            'title_ar' => [
                'string',
                'required',
            ],
            'title_en' => [
                'string',
                'required',
            ],
            'cities.*' => [
                'integer',
            ],
            'cities' => [
                'array',
            ],
            'user_id' => [
                'required',
                'integer',
            ],
            'branch_type_id' => [
                'required',
                'integer',
            ],
            'branch_category_id' => [
                'required',
                'integer',
            ],
        ];
    }
}
