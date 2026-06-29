<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    // تم حذف الربط بقسم التمويل الاستراتيجي
    protected $table = 'transactions';

    protected $fillable = [
        'balance',
        'debit',
        'credit',
        'transaction_description',
        'beneficiary_name',
        'date',
        'operation_type',
        'country',
        'risk_flag',
        'risk_score',
    ];
}
