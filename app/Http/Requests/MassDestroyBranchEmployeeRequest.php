<?php

namespace App\Http\Requests;

use App\Models\BranchEmployee;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyBranchEmployeeRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('branch_employee_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:branch_employees,id',
        ];
    }
}
