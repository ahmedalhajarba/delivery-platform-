<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatusOrderHistory extends Model
{
    use HasFactory;
    public $table = 'status_order_history';
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $fillable = [
        'order_id',
        'order_status',
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
    public function order_status()
    {
        return $this->belongsTo(OrderStatus::class, 'order_status', 'id');
    }
}
