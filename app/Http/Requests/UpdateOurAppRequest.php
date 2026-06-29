<?php

namespace App\Http\Requests;

use App\Models\OurApp;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateOurAppRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('our_app_edit');
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
            'android_store_link' => [
                'string',
                'nullable',
            ],
            'apple_store_link' => [
                'string',
                'nullable',
            ],
        ];
    }
}
