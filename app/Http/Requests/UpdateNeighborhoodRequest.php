<?php

namespace App\Http\Requests;

use App\Models\Neighborhood;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateNeighborhoodRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('neighborhood_edit');
    }

    public function rules()
    {
        return [
            'city_id' => [
                'required',
                'integer',
            ],
            'title_ar' => [
                'string',
                'required',
            ],
            'title_en' => [
                'string',
                'required',
            ],
            'slug' => [
                'string',
                'required',
            ],
            'type' => [
                'required',
            ],
        ];
    }
}
