<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderStatus extends Model
{
    use HasFactory;

    public $table = 'order_status';
    protected $fillable = ['name_ar','name_en'];
    protected $appends = ['name'];


    public function order()
    {
        return $this->hasMany(Order::class);
    }
    public function statusOrderHistory(){
        return $this->belongsTo( StatusOrderHistory::class);
    }
    public function getNameAttribute() // aseel
    {
        return app()->getLocale() === 'en' ? $this->name_en : $this->name_ar;
    }
}
