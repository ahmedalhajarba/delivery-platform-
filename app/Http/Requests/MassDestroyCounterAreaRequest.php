<?php

namespace App\Http\Requests;

use App\Models\CounterArea;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyCounterAreaRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('counter_area_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:counter_areas,id',
        ];
    }
}
