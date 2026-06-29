<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesDiscountCode extends Model
{
    use SoftDeletes;

    public $table = 'sales_discount_codes';

    protected $fillable = [
        'code',
        'title',
        'description',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'scope',
        'allowed_role',
        'starts_at',
        'ends_at',
        'usage_limit',
        'used_count',
        'is_active',
        'created_by',
        'owner_sales_user_id',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function customers()
    {
        return $this->belongsToMany(User::class, 'sales_discount_code_customers', 'sales_discount_code_id', 'user_id');
    }

    public function usages()
    {
        return $this->hasMany(SalesDiscountUsage::class, 'sales_discount_code_id');
    }

    public function ownerSalesUser()
    {
        return $this->belongsTo(User::class, 'owner_sales_user_id');
    }
}
