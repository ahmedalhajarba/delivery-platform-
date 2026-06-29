<?php

namespace App\Http\Requests;

use App\Models\CurrencyManagement;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyCurrencyManagementRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('currency_management_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:currency_managements,id',
        ];
    }
}
