<?php

namespace App\Http\Requests;

use App\Models\CounterArea;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateCounterAreaRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('counter_area_edit');
    }

    public function rules()
    {
        return [
            'number' => [
                'required',
                'integer',
                'min:-2147483648',
                'max:2147483647',
            ],
            'description' => [
                'string',
                'min:2',
                'required',
            ],
            'identifier' => [
                'required',
            ],
            'icon' => [
                'string',
                'nullable',
            ],
        ];
    }
}
