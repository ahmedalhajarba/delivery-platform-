<?php

namespace App\Models;

use \DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Region extends Model
{
    use SoftDeletes;
    use HasFactory;

    public $table = 'regions';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'country_id',
        'title_ar',
        'title_en',
        'slug',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // ============== العلاقات ==============

    /**
     * العلاقة مع المحافظات (تم تغيير اسم الدالة ليكون أكثر وضوحاً)
     */
    public function governorates()
    {
        return $this->hasMany(Governorate::class, 'region_id', 'id');
    }

    /**
     * العلاقة مع الدولة
     */
    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    /**
     * العلاقة مع المدن (عبر المحافظات)
     */
    public function cities()
    {
        return $this->hasManyThrough(City::class, Governorate::class, 'region_id', 'governorate_id');
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
     * الحصول على اسم المنطقة بالعربية
     */
    public function getNameArAttribute()
    {
        return $this->title_ar;
    }

    /**
     * الحصول على اسم المنطقة بالإنجليزية
     */
    public function getNameEnAttribute()
    {
        return $this->title_en;
    }

    /**
     * التحقق من أن المنطقة مفعلة
     */
    public function isActive()
    {
        return $this->deleted_at === null;
    }

    /**
     * نطاق البحث عن المناطق النشطة
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
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