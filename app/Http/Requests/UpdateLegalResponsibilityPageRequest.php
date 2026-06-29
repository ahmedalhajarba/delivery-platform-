<?php

namespace App\Http\Requests;

use App\Models\LegalResponsibilityPage;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateLegalResponsibilityPageRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('legal_responsibility_page_edit');
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
