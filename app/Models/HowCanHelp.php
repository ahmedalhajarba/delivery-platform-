<?php

namespace App\Models;

use \DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HowCanHelp extends Model
{
    use SoftDeletes;
    use HasFactory;

    public const ICON_SELECT = [
        'fa fa-shopping-bag'  => 'shopping-bag',
        'fa fa-youtube-play'  => 'youtube-play',
        'fa fa-plane'         => 'plane',
        'fa fa-rocket'        => 'rocket',
        'fa fa-gear'          => 'gear',
        'fa fa-cc-paypal'     => 'paypal',
        'fa fa-cc-stripe'     => 'stripe',
        'fa fa-cc-visa'       => 'visa',
        'fa fa-cc-mastercard' => 'mastercard',
        'fa fa-google-wallet' => 'wallet',
        'fa fa-paypal'        => 'paypal',
        'fa fa-heartbeat'     => 'heartbeat',
        'fa fa-money'         => 'money',
    ];

    public $table = 'how_can_helps';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'icon',
        'title_en',
        'title_ar',
        'description_en',
        'description_ar',
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    protected $appends = [
        'title',
        'description',
    ];


    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
    public function getTitleAttribute() // aseel
    {
        return app()->getLocale() === 'en' ? $this->title_en : $this->title_ar;
    }
    public function getDescriptionAttribute()
    {
        return app()->getLocale() === 'en' ? $this->description_en : $this->description_ar;
    }
}
