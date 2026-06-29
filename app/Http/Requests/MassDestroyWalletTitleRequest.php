<?php

namespace App\Http\Requests;

use App\Models\WalletTitle;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyWalletTitleRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('wallet_title_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:wallet_titles,id',
        ];
    }
}
