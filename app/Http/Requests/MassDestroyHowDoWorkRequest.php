<?php

namespace App\Http\Requests;

use App\Models\HowDoWork;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyHowDoWorkRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('how_do_work_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:how_do_works,id',
        ];
    }
}
