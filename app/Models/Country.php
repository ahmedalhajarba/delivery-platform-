<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Country extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'countries';

    protected $fillable = [
        'name',
        'short_code',
        'iso3',
        'branch_id',
        'responsible_user_id',
        'is_active',
        'allow_pickup',
        'allow_delivery',
        'allow_subscriptions',
        'allow_extra_services',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'allow_pickup' => 'boolean',
        'allow_delivery' => 'boolean',
        'allow_subscriptions' => 'boolean',
        'allow_extra_services' => 'boolean',
    ];

    // ============== العلاقات ==============

    public function cities()
    {
        return $this->hasMany(City::class);
    }

    public function regions()
    {
        return $this->hasMany(Region::class);
    }

    public function governorates()
    {
        return $this->hasMany(Governorate::class);
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function responsibleUser()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    // ============== دوال مساعدة ==============

    public function getCitiesCountAttribute()
    {
        return $this->cities()->count();
    }

    public function getRegionsCountAttribute()
    {
        return $this->regions()->count();
    }

    public function getGovernoratesCountAttribute()
    {
        return $this->governorates()->count();
    }

    public function isActive()
    {
        return $this->is_active == 1;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}