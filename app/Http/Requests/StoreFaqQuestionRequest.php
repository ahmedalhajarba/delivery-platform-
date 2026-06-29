<?php

namespace App\Http\Requests;

use App\Models\FaqQuestion;
use Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreFaqQuestionRequest extends FormRequest
{
    public function authorize()
    {
        return Gate::allows('faq_question_create');
    }

    public function rules()
    {
        return [
            'category_id' => [
                'required',
                'integer',
            ],
            'question' => [
                'required',
            ],
            'question_ar' => [
                'string',
                'min:1',
                'required',
            ],
            'answer' => [
                'required',
            ],
            'answer_ar' => [
                'required',
            ],
        ];
    }
}
