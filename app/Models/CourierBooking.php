<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourierBooking extends Model
{
    use HasFactory;

    public $table = 'courier_bookings';

    const SERVICE_TYPE = [
        'pickup' => 'استلام',
        'delivery' => 'تسليم',
        'exchange' => 'استبدال',
        'custom' => 'طلب خاص',
    ];

    const TRIP_DIRECTION = [
        'one_way' => 'ذهاب فقط',
        'round_trip' => 'ذهاب وعودة',
    ];

    const STATUS = [
        'new' => 'جديد',
        'confirmed' => 'مؤكد',
        'assigned' => 'تم الإسناد',
        'in_progress' => 'قيد التنفيذ',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغي',
    ];

    protected $fillable = [
        'booking_number',
        'user_id',
        'order_id',
        'sender_address_id',
        'recipient_address_id',
        'courier_id',
        'requested_date',
        'requested_time_from',
        'requested_time_to',
        'service_type',
        'trip_direction',
        'fee_amount',
        'is_paid',
        'status',
        'confirmed_at',
        'completed_at',
        'notes',
        'admin_notes',
    ];

    protected $casts = [
        'requested_date' => 'date',
        'is_paid' => 'boolean',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (CourierBooking $booking) {
            if (!$booking->booking_number) {
                $booking->booking_number = 'BKG-' . now()->format('YmdHis');
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function senderAddress()
    {
        return $this->belongsTo(Address::class, 'sender_address_id');
    }

    public function recipientAddress()
    {
        return $this->belongsTo(Address::class, 'recipient_address_id');
    }

    public function courier()
    {
        return $this->belongsTo(Courier::class, 'courier_id');
    }
}