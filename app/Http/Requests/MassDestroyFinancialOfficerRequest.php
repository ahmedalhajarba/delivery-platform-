<?php

namespace App\Http\Requests;

use App\Models\FinancialOfficer;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyFinancialOfficerRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('financial_officer_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:financial_officers,id',
        ];
    }
}
