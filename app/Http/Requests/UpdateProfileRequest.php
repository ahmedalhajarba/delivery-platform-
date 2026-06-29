<?php

namespace App\Http\Requests;

use App\Services\Validation\ContactValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        $dialCode = ContactValidation::normalizeDialCode(
            $this->input('mobile_country_code'),
            config('country_dial_codes.default', ContactValidation::COUNTRY_CODE)
        );

        $this->merge([
            'mobile_country_code' => $dialCode,
            'mobile' => ContactValidation::combineDialCodeAndNumber($dialCode, $this->input('mobile')),
            'email' => ContactValidation::normalizeEmail($this->input('email')),
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        abort_if(Gate::denies('profile_password_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $userId = auth()->id();

        return [
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'mobile' => [
                'required',
                ContactValidation::internationalMobileRegexRule(),
                Rule::unique('users', 'mobile')->ignore($userId),
            ],
            'mobile_country_code' => ['required', 'regex:/^\+\d{1,4}$/'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
        ];
    }

    public function messages()
    {
        return [
            'mobile.regex' => 'صيغة رقم الجوال غير صحيحة.',
            'mobile_country_code.required' => 'يرجى اختيار مقدمة الدولة.',
        ];
    }
}
