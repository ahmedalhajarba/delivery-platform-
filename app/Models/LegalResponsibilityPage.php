<?php

namespace App\Models;

use \DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class LegalResponsibilityPage extends Model implements HasMedia
{
    use SoftDeletes;
    use InteractsWithMedia;
    use HasFactory;

    public $table = 'legal_responsibility_pages';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'title_ar',
        'title_en',
        'text_ar',
        'text_en',
        'paragraph_ar',
        'paragraph_en',
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    
        protected $appends = [
        'title',
        'text',
    ];

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')->fit('crop', 50, 50);
        $this->addMediaConversion('preview')->fit('crop', 120, 120);
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
    
    
    public function getTitleAttribute() // aseel
    {
        return app()->getLocale() === 'en' ? $this->title_en : $this->title_ar;
    }
    
    public function getTextAttribute() // aseel
    {
        return app()->getLocale() === 'en' ? $this->text_en : $this->text_ar;
    }
}
