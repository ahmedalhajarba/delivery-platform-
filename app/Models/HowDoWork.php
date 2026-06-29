<?php

namespace App\Models;

use \DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class HowDoWork extends Model implements HasMedia
{
    use SoftDeletes;
    use InteractsWithMedia;
    use HasFactory;

    public $table = 'how_do_works';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    
     protected $appends = [
        'title_first_column',
        'des_first_column',
        'title_second_column',
        'des_second_column',
        'title_third_column',
        'des_third_column',
        'title_four_column',
        'des_four_column',
    ];

    protected $fillable = [
        'title_first_column_en',
        'des_first_column_en',
        'title_second_column_en',
        'des_second_column_en',
        'title_third_column_en',
        'des_third_column_en',
        'title_four_column_en',
        'des_four_column_en',
        'title_first_column_ar',
        'des_first_column_ar',
        'title_second_column_ar',
        'des_second_column_ar',
        'title_third_column_ar',
        'des_third_column_ar',
        'title_four_column_ar',
        'des_four_column_ar',
        'created_at',
        'updated_at',
        'deleted_at',
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
    
         public function getTitle_first_columnAttribute()
    {
        return app()->getLocale() === 'en' ? $this->title_first_column_en : $this->title_first_column_ar;
    }
    
             public function getDesFirstColumnAttribute()
    {
        return app()->getLocale() === 'en' ? $this->des_first_column_en : $this->des_first_column_ar;
    }
    
             public function getTitleSecondColumnAttribute()
    {
        return app()->getLocale() === 'en' ? $this->title_second_column_en : $this->title_second_column_ar;
    }
    
             public function getDesSecondColumnAttribute()
    {
        return app()->getLocale() === 'en' ? $this->des_second_column_en : $this->des_second_column_ar;
    }
    
    
             public function getTitleThirdColumnAttribute()
    {
        return app()->getLocale() === 'en' ? $this->title_third_column_en : $this->title_third_column_ar;
    }
    
             public function getDesThirdColumnAttribute()
    {
        return app()->getLocale() === 'en' ? $this->des_third_column_en : $this->des_third_column_ar;
    }
    
             public function getTitleFourColumnAttribute()
    {
        return app()->getLocale() === 'en' ? $this->title_four_column_en : $this->title_four_column_ar;
    }
    
             public function getDesFourColumnAttribute()
    {
        return app()->getLocale() === 'en' ? $this->des_four_column_en : $this->des_four_column_ar;
    }
}
