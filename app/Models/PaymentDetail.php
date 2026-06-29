<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentDetail extends Model
{
    use HasFactory;
    public $table = 'payment_details';
    protected $fillable =
        [
            'user_id',
            'payment_methode_id',
            'idcard',
            'status',
            'amount',
            'message',
            'active',
            'created_at',
            'updated_at',
            'deleted_at',
         ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function paymentMethodes()
    {
        return $this->belongsTo(PaymentMethode::class);
    }
}
