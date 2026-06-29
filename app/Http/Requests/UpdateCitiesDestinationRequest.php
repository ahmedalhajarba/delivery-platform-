<?php

namespace App\Http\Requests;

use App\Models\CitiesDestination;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class UpdateCitiesDestinationRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('cities_destination_edit');
    }

    public function rules()
    {
        return [
            'city_id' => [
                'required',
                'integer',
            ],
            'partner_id' => [
                'required',
                'integer',
            ],
            'destination' => [
                'string',
                'required',
            ],
        ];
    }
}
