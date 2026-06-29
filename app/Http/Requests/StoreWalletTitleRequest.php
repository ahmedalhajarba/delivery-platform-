<?php

namespace App\Http\Requests;

use App\Models\WalletTitle;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreWalletTitleRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('wallet_title_create');
    }

    public function rules()
    {
        return [
            'balance' => [
                'required',
            ],
            'user_id' => [
                'required',
                'integer',
            ],
            'type_id' => [
                'required',
                'integer',
            ],
            'follow' => [
                'nullable',
                'integer',
                'min:-2147483648',
                'max:2147483647',
            ],
        ];
    }
}
