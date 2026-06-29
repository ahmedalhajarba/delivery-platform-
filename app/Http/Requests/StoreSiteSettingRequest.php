<?php

namespace App\Http\Requests;

use App\Models\SiteSetting;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreSiteSettingRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('site_setting_create');
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
                'nullable',
            ],
            'logo' => [
                'required',
            ],
            'site_footer' => [
                'required',
            ],
            'live_chat_script' => [
                'nullable',
                'string',
            ],
            'email' => [
                'required',
            ],
            'phone' => [
                'string',
                'nullable',
            ],
            'mobile' => [
                'string',
                'required',
            ],
            'mobile_b' => [
                'string',
                'nullable',
            ],
            'mobile_c' => [
                'string',
                'nullable',
            ],
            'ios_url' => [
                'string',
                'nullable',
            ],
            'android_url' => [
                'string',
                'nullable',
            ],
            'harmony_url' => [
                'string',
                'nullable',
            ],
        ];
    }
}
