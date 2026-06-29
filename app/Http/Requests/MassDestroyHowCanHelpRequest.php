<?php

namespace App\Http\Requests;

use App\Models\HowCanHelp;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyHowCanHelpRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('how_can_help_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:how_can_helps,id',
        ];
    }
}
