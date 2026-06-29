<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OperationalSetting extends Model
{
    use HasFactory;

    public $table = 'operational_settings';

    protected $fillable = [
        'group_name',
        'key',
        'value_type',
        'value',
        'description',
    ];

    public static function getValue(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();

        if (!$setting) {
            return $default;
        }

        switch ($setting->value_type) {
            case 'boolean':
                return filter_var($setting->value, FILTER_VALIDATE_BOOLEAN);
            case 'number':
                return is_numeric($setting->value) ? (float) $setting->value : $default;
            case 'json':
                return $setting->value ? json_decode($setting->value, true) : $default;
            default:
                return $setting->value ?? $default;
        }
    }

    public static function setValue(string $key, $value, string $type = 'string', string $group = 'general', ?string $description = null): self
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'group_name' => $group,
                'value_type' => $type,
                'value' => is_array($value) ? json_encode($value) : (string) $value,
                'description' => $description,
            ]
        );
    }
}