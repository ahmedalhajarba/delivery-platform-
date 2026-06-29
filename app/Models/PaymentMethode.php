<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethode extends Model
{
    use HasFactory;
    public $table = 'payment_methodes';
    protected $fillable =
        [
            'name_en',
            'name_ar',
            'logo',
            'our_acount',
            'note_en',
            'note_ar',
            'active',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

    public function PaymentDetail()
    {
        return $this->hasMany(PaymentDetail::class, 'payment_methode_id', 'id');
    }
}
