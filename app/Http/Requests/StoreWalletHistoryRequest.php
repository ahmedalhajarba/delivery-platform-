<?php

namespace App\Http\Requests;

use App\Models\WalletHistory;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreWalletHistoryRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('wallet_history_create');
    }

    public function rules()
    {
        return [
            'wallet_id' => [
                'required',
                'integer',
            ],
            'value' => [
                'required',
            ],
        ];
    }
}
