<?php

namespace App\Models;

use \DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CounterArea extends Model
{
    use SoftDeletes;
    use HasFactory;

    public const IDENTIFIER_SELECT = [
        'kilo'    => 'K',
        'Million' => 'M',
        'Billion' => 'B',
    ];

    public $table = 'counter_areas';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'number',
        'description_en',
        'identifier',
        'icon',
        'created_at',
        'updated_at',
        'deleted_at',
        'description_ar',
    ];
    protected $appends = [
        'description',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function getDescriptionAttribute()
    {
        return app()->getLocale() === 'en' ? $this->description_en : $this->description_ar;
    }
}
