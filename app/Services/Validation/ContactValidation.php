<?php

namespace App\Services\Validation;

class ContactValidation
{
    public const COUNTRY_CODE = '+972';

    public static function normalizeEmail(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(mb_strtolower($value));

        return $value === '' ? null : $value;
    }

    public static function normalizeMobile(?string $value): ?string
    {
        return self::normalizeLocalNumber($value);
    }

    public static function normalizeLocalNumber(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/\D+/', '', trim($value));

        return $value === '' ? null : $value;
    }

    public static function normalizeDialCode(?string $dialCode, string $default = self::COUNTRY_CODE): string
    {
        $digits = preg_replace('/\D+/', '', (string) $dialCode);
        if ($digits === '') {
            $digits = preg_replace('/\D+/', '', $default) ?: '972';
        }

        return '+' . $digits;
    }

    public static function combineDialCodeAndNumber(?string $dialCode, ?string $localNumber, string $defaultDialCode = self::COUNTRY_CODE): ?string
    {
        $local = self::normalizeLocalNumber($localNumber);
        if ($local === null) {
            return null;
        }

        $dial = self::normalizeDialCode($dialCode, $defaultDialCode);

        return preg_replace('/\D+/', '', $dial) . $local;
    }

    public static function localMobileRegexRule(): string
    {
        return 'regex:/^5\d{8}$/';
    }

    public static function internationalMobileRegexRule(): string
    {
        return 'regex:/^[1-9]\d{5,14}$/';
    }

    public static function splitDialCodeAndLocalNumber(?string $storedNumber, array $dialCodes, string $fallbackDialCode = self::COUNTRY_CODE): array
    {
        $number = self::normalizeLocalNumber($storedNumber);
        $fallback = self::normalizeDialCode($fallbackDialCode);

        if ($number === null) {
            return [
                'dial_code' => $fallback,
                'local_number' => null,
            ];
        }

        foreach ($dialCodes as $dialCode) {
            $code = self::normalizeDialCode($dialCode);
            $codeDigits = preg_replace('/\D+/', '', $code);
            if (str_starts_with($number, $codeDigits) && strlen($number) > strlen($codeDigits)) {
                return [
                    'dial_code' => $code,
                    'local_number' => substr($number, strlen($codeDigits)),
                ];
            }
        }

        return [
            'dial_code' => $fallback,
            'local_number' => $number,
        ];
    }
}
