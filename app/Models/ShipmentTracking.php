<?php

namespace App\Models;

use App\Services\SubscriptionService;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ShipmentTracking extends Model
{
    use HasFactory;

    public $table = 'shipment_tracking';

    protected $fillable = [
        'order_id',
        'tracking_status_id',
        'branch_id',
        'courier_id',
        'courier_assignment_id',
        'carrier_company_id',
        'carrier_waybill_number',
        'recorded_by',
        'updated_by',
        'updated_by_role',
        'location_description',
        'latitude',
        'longitude',
        'notes',
        'event_time',
    ];

    protected $casts = [
        'latitude'   => 'decimal:7',
        'longitude'  => 'decimal:7',
        'event_time' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        // عند إضافة تتبع جديد: تحديث حالة الطلب تلقائياً
        static::created(function (ShipmentTracking $tracking) {
            $order = $tracking->order;
            if ($order) {
                // تحديث حالة الطلب في status_order_history
                StatusOrderHistory::create([
                    'order_id'     => $tracking->order_id,
                    'order_status' => $tracking->tracking_status_id,
                    'branch_id'    => $tracking->branch_id,
                    'courier_id'   => $tracking->courier_id,
                    'notes'        => $tracking->notes,
                    'event_time'   => $tracking->event_time,
                ]);

                // خصم شحنة الاشتراك عند أول وصول لحالة PICKED_UP فقط
                $statusCode = $tracking->trackingStatus?->code;
                if (!$statusCode && $tracking->tracking_status_id) {
                    $statusCode = TrackingStatus::where('id', $tracking->tracking_status_id)->value('code');
                }

                if ($statusCode === 'PICKED_UP' && (string) $order->order_type === 'subscription') {
                    try {
                        app(SubscriptionService::class)->consumeShipmentOnPickup($order, $tracking->carrier_company_id);
                    } catch (\Throwable $e) {
                        Log::warning('Failed to consume subscription shipment on PICKED_UP', [
                            'order_id' => $order->id,
                            'tracking_id' => $tracking->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        });
    }

    // ========== Relationships ==========

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function trackingStatus()
    {
        return $this->belongsTo(TrackingStatus::class, 'tracking_status_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function courier()
    {
        return $this->belongsTo(BranchEmployee::class, 'courier_id');
    }

    public function courierAssignment()
    {
        return $this->belongsTo(CourierAssignment::class, 'courier_assignment_id');
    }

    public function carrierCompany()
    {
        return $this->belongsTo(CarrierCompany::class, 'carrier_company_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
