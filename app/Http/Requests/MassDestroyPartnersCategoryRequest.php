<?php

namespace App\Http\Requests;

use App\Models\PartnersCategory;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyPartnersCategoryRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('partners_category_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:partners_categories,id',
        ];
    }
}
