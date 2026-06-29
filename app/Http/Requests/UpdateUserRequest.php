<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\Validation\ContactValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
            'login_code' => $this->normalizeLoginCode($this->input('login_code')),
        ]);
    }

    private function normalizeLoginCode($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $normalized = strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);

        $digits = preg_replace('/\D+/', '', $normalized) ?? '';
        if (strlen($digits) === 9) {
            return substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6, 3);
        }

        return $normalized;
    }

    public function authorize()
    {
        return Gate::allows('user_edit');
    }

    public function rules()
    {
        return [
            'name' => [
                'string',
                'required',
            ],
            'last_name' => [
                'string',
                'nullable',
            ],
            'username' => [
                'required',
                'string',
                'max:100',
                'regex:/^[\p{Arabic}\p{L}\p{N}._-]+$/u',
                Rule::unique('users', 'username')->ignore(request()->route('user')->id),
            ],
            'login_code' => [
                'nullable',
                'regex:/^\d{3}-\d{3}-\d{3}$/',
                Rule::unique('users', 'login_code')->ignore(request()->route('user')->id),
            ],
            'mobile' => [
                'required',
                ContactValidation::internationalMobileRegexRule(),
                Rule::unique('users', 'mobile')->ignore(request()->route('user')->id),
            ],
            'mobile_country_code' => [
                'required',
                'regex:/^\+\d{1,4}$/',
            ],
            'city_id' => [
                'required',
                'integer',
            ],
            'email' => [
                'nullable',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore(request()->route('user')->id),
            ],
            'roles.*' => [
                'integer',
            ],
            'roles' => [
                'required',
                'array',
            ],
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
