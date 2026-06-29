<?php

namespace App\Http\Requests;

use App\Models\HowCanHelp;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateHowCanHelpRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('how_can_help_edit');
    }

    public function rules()
    {
        return [
            'title_en' => [
                'string',
                'required',
            ],
            'title_ar' => [
                'string',
                'required',
            ],
            'description_en' => [
                'string',
                'required',
            ],
            'description_ar' => [
                'string',
                'required',
            ],
        ];
    }
}
