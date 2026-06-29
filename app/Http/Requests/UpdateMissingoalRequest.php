<?php

namespace App\Http\Requests;

use App\Models\Missingoal;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateMissingoalRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('missingoal_edit');
    }

    public function rules()
    {
        return [
            'mission' => [
                'required',
            ],
            'mission_ar' => [
                'required',
            ],
            'vision' => [
                'required',
            ],
            'vision_ar' => [
                'required',
            ],
            'goal' => [
                'required',
            ],
            'goal_ar' => [
                'required',
            ],
        ];
    }
}
