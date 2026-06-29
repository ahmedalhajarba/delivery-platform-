<?php

namespace App\Models;

use \DateTimeInterface;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SubscriptionsPlan extends Model implements HasMedia
{
    use SoftDeletes;
    use InteractsWithMedia;
    use Auditable;
    use HasFactory;

    public const STATUS_SELECT = [
        '0' => 'On',
        '1' => 'Off',
    ];

    public const STORE_TYPE_RADIO = [
        '0' => 'none',
        '1' => 'Big Store',
        '2' => 'Small Store',
    ];
    
        public const BUSINESS_TYPE_SELECT = [
        '1' => 'Individuals',
        '2' => 'Business',
        '3'=>'shops',
        '4'=>'Companys',
    ];

    public $table = 'subscriptions_plans';

    protected $appends = [
        'image',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'category_id',
        'title_ar',
        'title_en',
        'm_price',
        'subscription_period',
        'description_ar',
        'description_en',
        'status',
        'store_type',
        'orders_count',
        'order_price',
        'subscription_period_id',
        'business_type',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')->fit('crop', 50, 50);
        $this->addMediaConversion('preview');
    }

    public function subscriptionUserSubscriptions()
    {
        return $this->hasMany(UserSubscription::class, 'subscription_id', 'id');
    }

    public function category()
    {
        return $this->belongsTo(SubscriptionsCategory::class, 'category_id');
    }

    public function getImageAttribute()
    {
        $file = $this->getMedia('image')->last();
        if ($file) {
            $file->url       = $file->getUrl();
            $file->thumbnail = $file->getUrl('thumb');
            $file->preview   = $file->getUrl('preview');
        }

        return $file;
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
    public function getTitleAttribute() // aseel
    {
        return app()->getLocale() === 'en' ? $this->title_en : $this->title_ar;
    }

    public function subscriptionPlanFeaturesSubscribes()
    {
        return $this->hasMany(FeaturesSubscribe::class, 'subscription_plan_id', 'id');
    }
    
    public function subscription_period()
    {
        return $this->belongsTo(SubscriptionPeriod::class, 'subscription_period_id');
    }
}
