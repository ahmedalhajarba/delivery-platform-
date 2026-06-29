<?php

namespace App\Http\Requests;

use App\Models\WalletType;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

class MassDestroyWalletTypeRequest extends FormRequest
{
    public function authorize()
    {
        abort_if(Gate::denies('wallet_type_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    public function rules()
    {
        return [
            'ids'   => 'required|array',
            'ids.*' => 'exists:wallet_types,id',
        ];
    }
}
