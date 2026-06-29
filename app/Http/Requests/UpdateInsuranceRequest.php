<?php

namespace App\Http\Requests;

use App\Models\Insurance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class UpdateInsuranceRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('insurance_edit');
    }

    public function rules()
    {
        return [
            'insurance_rate' => [
                'required',
                'integer',
                'min:-2147483648',
                'max:2147483647',
            ],
            'order_id' => [
                'required',
                'integer',
                'exists:orders,id',
            ],
            'invoice_id' => [
                'nullable',
                'integer',
                'exists:invoices,id',
            ],
            'receipt_id' => [
                'nullable',
                'integer',
                'exists:receipts,id',
            ],
            'original_receipt_number' => [
                'nullable',
                'string',
                'max:100',
            ],
            'status' => [
                'nullable',
                'in:issued,active,expired,cancelled,claimed',
            ],
            'issued_at' => [
                'nullable',
                'date',
            ],
            'start_date' => [
                'nullable',
                'date',
            ],
            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],
            'terms_and_conditions' => [
                'nullable',
                'string',
            ],
        ];
    }
}
