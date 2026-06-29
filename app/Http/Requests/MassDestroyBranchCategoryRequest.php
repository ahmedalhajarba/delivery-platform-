<?php

namespace App\Http\Requests;

use App\Models\BranchCategory;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyBranchCategoryRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('branch_category_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:branch_categories,id',
        ];
    }
}
