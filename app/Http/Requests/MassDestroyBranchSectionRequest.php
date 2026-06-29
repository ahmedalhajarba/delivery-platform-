<?php

namespace App\Http\Requests;

use App\Models\BranchSection;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyBranchSectionRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('branch_section_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:branch_sections,id',
        ];
    }
}
