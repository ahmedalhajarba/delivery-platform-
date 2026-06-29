<?php

namespace App\Http\Requests;

use App\Models\Region;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreRegionRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('region_create');
    }

    public function rules()
    {
        return [
            'country_id' => [
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
        ];
    }
}
