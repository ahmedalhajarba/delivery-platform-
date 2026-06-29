<?php

namespace App\Models;

use \DateTimeInterface;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeaturesSubscribe extends Model
{
    use SoftDeletes;
    use Auditable;
    use HasFactory;

    public const STATUS_SELECT = [
        '0' => 'On',
        '1' => 'Off',
    ];

    public const TYPE_RADIO = [
        '0' => 'Basic',
        '1' => 'Advance',
    ];

    public const VALUE_CHEK_RADIO = [
        '0' => 'Price',
        '1' => 'True',
        '2' => 'False',
    ];

    public $table = 'features_subscribes';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'subscription_plan_id',
        'type',
        'title_ar',
        'title_en',
        'description_ar',
        'description_en',
        'status',
        'bronze_value_en',
        'bronze_value_ar',
        'silver_value_en',
        'silver_value_ar',
        'gold_value_en',
        'gold_value_ar',
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    protected $appends = [
        'title',
        'bronze_value',
        'silver_value',
        'gold_value',
    ];
    public function featureFeaturesSubscribeRelations()
    {
        return $this->hasMany(FeaturesSubscribeRelation::class, 'feature_id', 'id');
    }

    public function subscription_plan()
    {
        return $this->belongsTo(SubscriptionsPlan::class, 'subscription_plan_id');
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
    
    public function getBronzeValueAttribute(){

        if(\App::isLocale('ar')){
            return $this->bronze_value_ar;
        }else{
            return $this->bronze_value_en;
        }
    }
    
    public function getSilverValueAttribute(){

        if(\App::isLocale('ar')){
            return $this->silver_value_ar;
        }else{
            return $this->silver_value_en;
        }
    }
    
    public function getGoldValueAttribute(){

        if(\App::isLocale('ar')){
            return $this->gold_value_ar;
        }else{
            return $this->gold_value_en;
        }
    }
}
