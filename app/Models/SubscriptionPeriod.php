<?php

namespace App\Models;

use \DateTimeInterface;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionPeriod extends Model
{
    use SoftDeletes;
    use Auditable;
    use HasFactory;

    public const STATUS_RADIO = [
        '1' => 'On',
        '0' => 'Off',
    ];
    protected $appends = [
        'title',
    ];

    public $table = 'subscription_periods';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'title_ar',
        'title_en',
        'period',
        'status',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function subscriptionPeriodSubscriptionsPlans()
    {
        return $this->hasMany(SubscriptionsPlan::class, 'subscription_period_id', 'id');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function getTitleAttribute(){

        if(\App::isLocale('ar')){
            return $this->title_ar;
        }else{
            return $this->title_en;
        }
    }
}
