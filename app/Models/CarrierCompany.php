<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarrierCompany extends Model
{
    use HasFactory, SoftDeletes;

    public $table = 'carrier_companies';

    protected $fillable = [
        'name_ar', 'name_en', 'code', 'logo',
        'api_endpoint', 'api_key', 'api_secret', 'account_number',
        'contact_person', 'contact_phone', 'contact_email',
        'is_active', 'has_api_integration', 'notes',
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'has_api_integration'  => 'boolean',
    ];

    // إخفاء البيانات الحساسة
    protected $hidden = ['api_key', 'api_secret'];

    public function waybills()
    {
        return $this->hasMany(CarrierWaybill::class, 'carrier_company_id');
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'en' ? $this->name_en : $this->name_ar;
    }
}
