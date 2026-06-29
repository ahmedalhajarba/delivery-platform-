<?php

namespace App\Models;

use \DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Governorate extends Model
{
    use SoftDeletes;
    use HasFactory;

    public $table = 'governorates';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'title_ar',
        'title_en',
        'slug',
        'region_id',
        'country_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // ============== العلاقات ==============

    /**
     * العلاقة مع المنطقة
     */
    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    /**
     * العلاقة مع الدولة
     */
    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    /**
     * العلاقة مع المدن
     */
    public function governorateCities()
    {
        return $this->hasMany(City::class, 'governorate_id', 'id');
    }

    // ============== دوال مساعدة ==============

    /**
     * الحصول على الاسم حسب اللغة
     */
    public function getTitleAttribute()
    {
        return app()->getLocale() === 'en' ? $this->title_en : $this->title_ar;
    }

    /**
     * الحصول على اسم المحافظة بالعربية
     */
    public function getNameArAttribute()
    {
        return $this->title_ar;
    }

    /**
     * الحصول على اسم المحافظة بالإنجليزية
     */
    public function getNameEnAttribute()
    {
        return $this->title_en;
    }

    /**
     * التحقق من أن المحافظة مفعلة
     */
    public function isActive()
    {
        return $this->deleted_at === null;
    }

    /**
     * نطاق البحث عن المحافظات النشطة
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * نطاق البحث حسب المنطقة
     */
    public function scopeByRegion($query, $regionId)
    {
        return $query->where('region_id', $regionId);
    }

    /**
     * نطاق البحث حسب الدولة
     */
    public function scopeByCountry($query, $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    // ============== Serialization ==============

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}