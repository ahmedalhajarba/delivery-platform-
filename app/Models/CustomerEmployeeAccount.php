<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerEmployeeAccount extends Model
{
    use HasFactory;

    public $table = 'customer_employee_accounts';

    protected $fillable = [
        'customer_user_id',
        'employee_user_id',
        'credit_application_id',
        'slot_no',
        'role_ar',
        'role_en',
        'is_active',
        'last_provisioned_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_provisioned_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_user_id');
    }

    public function application()
    {
        return $this->belongsTo(CustomerCreditApplication::class, 'credit_application_id');
    }
}
