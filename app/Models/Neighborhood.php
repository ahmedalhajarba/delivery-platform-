<?php

namespace App\Models;

use \DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Neighborhood extends Model
{
    use SoftDeletes;
    use HasFactory;

    public const TYPE_RADIO = [
        '0' => 'nearby village',
        '1' => 'far away village',
        '2'=> 'neighborhood'
    ];

    public $table = 'neighborhoods';
    protected $appends = [
        'title',
    ];
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'city_id',
        'title_ar',
        'title_en',
        'slug',
        'type',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
         public function getTitleAttribute() // aseel
    {
        return app()->getLocale() === 'en' ? $this->title_en : $this->title_ar;
    }
    
}
