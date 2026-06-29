<?php

namespace App\Http\Requests;

use App\Models\BranchType;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreBranchTypeRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('branch_type_create');
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
