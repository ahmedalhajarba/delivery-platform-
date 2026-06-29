<?php

namespace App\Http\Requests;

use App\Models\BranchSection;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreBranchSectionRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('branch_section_create');
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
            'user_id' => [
                'required',
                'integer',
            ],
        ];
    }
}
