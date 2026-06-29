<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionSettingOption extends Model
{
    public $table = 'subscription_setting_options';

    protected $fillable = [
        'type',
        'code',
        'name_ar',
        'name_en',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
