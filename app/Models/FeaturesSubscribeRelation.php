<?php

namespace App\Models;

use \DateTimeInterface;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeaturesSubscribeRelation extends Model
{
    use SoftDeletes;
    use Auditable;
    use HasFactory;

    public $table = 'features_subscribe_relations';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'feature_id',
        'subscription_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function feature()
    {
        return $this->belongsTo(FeaturesSubscribe::class, 'feature_id');
    }

    public function subscription()
    {
        return $this->belongsTo(SubscriptionsPlan::class, 'subscription_id');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
