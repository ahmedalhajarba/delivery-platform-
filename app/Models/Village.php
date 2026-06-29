<?php

namespace App\Models;

use \DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Village extends Model
{
    use SoftDeletes;
    use HasFactory;

    public const TYPE_RADIO = [
        '0' => 'nearby village',
        '1' => 'far away village',
    ];

    public $table = 'villages';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'governorate_id',
        'title_ar',
        'title_en',
        'slug',
        'type',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function governorate()
    {
        return $this->belongsTo(Governorate::class, 'governorate_id');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
