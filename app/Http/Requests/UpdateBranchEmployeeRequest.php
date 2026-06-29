<?php

namespace App\Http\Requests;

use App\Models\BranchEmployee;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateBranchEmployeeRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('branch_employee_edit');
    }

    public function rules()
    {
        return [
            'name' => [
                'string',
                'required',
            ],
            'job_title_ar' => [
                'string',
                'required',
            ],
            'job_title_en' => [
                'string',
                'required',
            ],
            'jobid' => [
                'string',
                'required',
            ],
            'branch_id' => [
                'required',
                'integer',
            ],
            'mobile' => [
                'string',
                'nullable',
            ],
            'nationality' => [
                'string',
                'nullable',
            ],
            'country_id' => [
                'required',
                'integer',
            ],
            'city_id' => [
                'required',
                'integer',
            ],
        ];
    }
}
