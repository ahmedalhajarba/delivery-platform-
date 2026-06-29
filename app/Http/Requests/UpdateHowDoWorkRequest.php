<?php

namespace App\Http\Requests;

use App\Models\HowDoWork;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateHowDoWorkRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('how_do_work_edit');
    }

    public function rules()
    {
        return [
            'title_first_column' => [
                'string',
                'required',
            ],
            'des_first_column' => [
                'required',
            ],
            'title_second_column' => [
                'string',
                'required',
            ],
            'des_second_column' => [
                'string',
                'required',
            ],
            'title_third_column' => [
                'string',
                'required',
            ],
            'des_third_column' => [
                'required',
            ],
            'title_four_column' => [
                'string',
                'required',
            ],
            'des_four_column' => [
                'string',
                'required',
            ],
        ];
    }
}
