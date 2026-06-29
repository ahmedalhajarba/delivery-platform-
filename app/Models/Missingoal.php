<?php

namespace App\Models;

use \DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Missingoal extends Model implements HasMedia
{
    use SoftDeletes;
    use InteractsWithMedia;
    use HasFactory;

    public $table = 'missingoals';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'mission_en',
        'mission_ar',
        'vision_en',
        'vision_ar',
        'goal_en',
        'goal_ar',
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    
        protected $appends = [
        'mission',
        'vision',
        'goal',
    ];

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')->fit('crop', 50, 50);
        $this->addMediaConversion('preview');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
    
     public function getMissionAttribute()
    {
        return app()->getLocale() === 'en' ? $this->mission_en : $this->mission_ar;
    }
         public function getVisionAttribute()
    {
        return app()->getLocale() === 'en' ? $this->vision_en : $this->vision_ar;
    }
         public function getGoalAttribute()
    {
        return app()->getLocale() === 'en' ? $this->goal_en : $this->goal_ar;
    }
}
