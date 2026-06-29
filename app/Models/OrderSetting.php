<?php

namespace App\Models;

use \DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderSetting extends Model
{
    use SoftDeletes;
    use HasFactory;

    public const NOTE_RADIO = [
        '0' => 'off',
        '1' => 'on',
    ];

    public const WIDTH_RADIO = [
        '0' => 'off',
        '1' => 'on',
    ];

    public const SENDER_RADIO = [
        '0' => 'off',
        '1' => 'on',
    ];

    public const LENGTH_RADIO = [
        '0' => 'off',
        '1' => 'on',
    ];

    public const HEIGHT_RADIO = [
        '0' => 'off',
        '1' => 'on',
    ];

    public const RECIPIENT_RADIO = [
        '0' => 'off',
        '1' => 'on',
    ];

    public const ACTUAL_WEIGHT_RADIO = [
        '0' => 'off',
        '1' => 'on',
    ];

    public const STATED_VALUE_RADIO = [
        '0' => 'off',
        '1' => 'on',
    ];

    public const PACKAGES_COUNT_RADIO = [
        '0' => 'off',
        '1' => 'on',
    ];

    public const PACKAGE_WEIGHT_RADIO = [
        '0' => 'off',
        '1' => 'on',
    ];

    public const PACKAGE_CONTENT_RADIO = [
        '0' => 'off',
        '1' => 'on',
    ];

    public const REFERENCE_NUMBER_RADIO = [
        '0' => 'off',
        '1' => 'on',
    ];

    public const SHIPMENT_TYPE_RADIO = [
        '0' => 'document',
        '1' => 'package',
    ];

    public $table = 'order_settings';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'insurance_rate',
        'divided_number',
        'print_copies',
        'shipping_rate',
        'allowed_weight',
        'print_settings',
        'sender',
        'recipient',
        'shipment_type',
        'package_content',
        'packages_count',
        'package_weight',
        'actual_weight',
        'length',
        'width',
        'height',
        'stated_value',
        'reference_number',
        'note',
        'created_at',
        'updated_at',
        'deleted_at',
        'over_weight_cost',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
