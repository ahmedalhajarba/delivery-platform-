<?php

namespace App\Http\Requests;

use App\Models\TermsCondition;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyTermsConditionRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('terms_condition_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:terms_conditions,id',
        ];
    }
}
