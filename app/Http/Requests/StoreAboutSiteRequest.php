<?php

namespace App\Http\Requests;

use App\Models\AboutSite;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreAboutSiteRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('about_site_create');
    }

    public function rules()
    {
        return [
            'title' => [
                'string',
                'min:2',
                'required',
            ],
            'title_ar' => [
                'string',
                'required',
            ],
            'breif' => [
                'required',
            ],
            'breif_ar' => [
                'string',
                'required',
            ],
            'description' => [
                'required',
            ],
            'description_ar' => [
                'required',
            ],
            'photo_logo' => [
                'required',
            ],
        ];
    }
}
