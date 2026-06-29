<?php

namespace App\Models;

use \DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class City extends Model
{
    use SoftDeletes;
    use HasFactory;

    public $table = 'cities';

    public const TYPE_RADIO = [
        '0' => 'city',
        '1' => 'village',
    ];

    protected $appends = [
        'title',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'country_id',
        'governorate_id',
        'title_ar',
        'title_en',
        'type',
        'slug',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // ============== العلاقات ==============

    /**
     * العلاقة مع المحافظة
     */
    public function governorate()
    {
        return $this->belongsTo(Governorate::class, 'governorate_id');
    }

    /**
     * العلاقة مع الدولة
     */
    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    /**
     * العلاقة مع المنطقة (يتم الوصول إليها عبر المحافظة)
     * ملاحظة: جدول cities لا يحتوي على region_id مباشراً
     */
    public function getRegionAttribute()
    {
        return $this->governorate?->region;
    }

    /**
     * العلاقة مع المستخدمين
     */
    public function cityUsers()
    {
        return $this->hasMany(User::class, 'city_id', 'id');
    }

    /**
     * العلاقة مع الشركات
     */
    public function cityCompanies()
    {
        return $this->hasMany(Company::class, 'city_id', 'id');
    }

    /**
     * العلاقة مع موظفي الفروع
     */
    public function cityBranchEmployees()
    {
        return $this->hasMany(BranchEmployee::class, 'city_id', 'id');
    }

    /**
     * العلاقة مع الأحياء
     */
    public function cityNeighborhoods()
    {
        return $this->hasMany(Neighborhood::class, 'city_id', 'id');
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
     * الحصول على اسم المدينة بالعربية
     */
    public function getNameArAttribute()
    {
        return $this->title_ar;
    }

    /**
     * الحصول على اسم المدينة بالإنجليزية
     */
    public function getNameEnAttribute()
    {
        return $this->title_en;
    }

    /**
     * التحقق من أن المدينة مفعلة
     */
    public function isActive()
    {
        return $this->deleted_at === null;
    }

    /**
     * نطاق البحث عن المدن النشطة
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * نطاق البحث حسب المحافظة
     */
    public function scopeByGovernorate($query, $governorateId)
    {
        return $query->where('governorate_id', $governorateId);
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