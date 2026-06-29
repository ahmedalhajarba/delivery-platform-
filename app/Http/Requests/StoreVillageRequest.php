<?php

namespace App\Http\Requests;

use App\Models\Village;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreVillageRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('village_create');
    }

    public function rules()
    {
        return [
            'governorate_id' => [
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
