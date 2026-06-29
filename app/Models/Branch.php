<?php

namespace App\Models;

use \DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use SoftDeletes;
    use HasFactory;

    public $table = 'branches';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'number',
        'title_ar',
        'title_en',
        'country_id',
        'user_id',
        'branch_type_id',
        'branch_category_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $appends = ['title'];

    public function branchBranchEmployees()
    {
        return $this->hasMany(BranchEmployee::class, 'branch_id', 'id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function cities()
    {
        return $this->belongsToMany(City::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function branch_type()
    {
        return $this->belongsTo(BranchType::class, 'branch_type_id');
    }

    public function branch_category()
    {
        return $this->belongsTo(BranchCategory::class, 'branch_category_id');
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
