<?php

namespace App\Models;

use \DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;
    use HasFactory;

    public const SHIPMENT_TYPE_RADIO = [
        '0' => 'document',
        '1' => 'package',
    ];

    public const PACKAGE_TYPE = [
        'box'      => 'صندوق (Box)',
        'envelope' => 'ظرف (Envelope)',
        'bag'      => 'كيس (Bag)',
        'pallet'   => 'بالة (Pallet)',
        'other'    => 'أخرى (Other)',
    ];

    public const ORDER_TYPE = [
        'subscription' => 'اشتراك مدفوع مسبقاً',
        'deferred'     => 'آجل الدفع',
        'single'       => 'شحنة فردية',
    ];

    public const PAYMENT_STATUS = [
        'pending'   => 'بانتظار الدفع',
        'paid'      => 'مدفوع',
        'deferred'  => 'مؤجل',
        'cancelled' => 'ملغي',
    ];

    public $table = 'orders';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'subscription_deducted_at',
    ];

    protected $fillable = [
        'user_id',
        'sender_id',
        'recipient_id',
        'package_type',
        'shipment_type',
        'package_content',
        'packages_count',
        'package_weight',
        'stated_value',
        'reference_number',
        'note',
        'created_at',
        'updated_at',
        'deleted_at',
        'order_status_id',
        'print_order_count',
        'response',
        'final_shopping_cost',
        'our_partner_id',
        'bill_url',
        'order_type',
        'payment_status',
        'waybill_number',
        'load_id',
        'load_description',
        'shipment_category',
        'actual_weight',
        'volumetric_weight',
        'chargeable_weight',
        'origin_branch_id',
        'destination_branch_id',
        'assigned_courier_id',
        'user_subscription_id',
        'subscription_deducted_at',
        'consumed_carrier_company_id',
        'invoice_id',
        'shipping_type',
        'delivery_speed',
        'speed_surcharge',
        'shipping_cost',
        'over_weight_cost',
        'packaging_cost',
        'storage_cost',
        'storage_days',
        'insurance_cost',
        'vat_amount',
        'total_cost',
        'cod_amount',
        'delivery_type',
        'intl_destination_country_id',
        'intl_surcharge',
    ];

    // تفعيل cascade soft-delete
    protected static function boot()
    {
        parent::boot();
        self::deleted(function ($order) {
            $order->orderInsurances()->delete();
            $order->statusOrderHistory()->delete();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function orderInsurances()
    {
        return $this->hasMany(Insurance::class);
    }

    public function sender()
    {
        return $this->belongsTo(Address::class, 'sender_id');
    }
    
    public function statusOrderHistory(){

        return $this->hasMany(StatusOrderHistory::class);

    }
    public function lastStatusOrderHistory(){

        return $this->hasMany(StatusOrderHistory::class,'order_id')
            ->orderBy('created_at','DESC');

    }

    public function recipient()
    {
        return $this->belongsTo(Address::class, 'recipient_id');
    }


    public function partner()
    {
        return $this->belongsTo(OurPartner::class, 'our_partner_id');
    }

    public function volumetricWeightDetail(){
        return $this->hasMany(VolumetricWeightDetail::class);

    }


    public function order_status()
    {
        return $this->belongsTo(OrderStatus::class, 'order_status_id');
    }

    public function status()
    {
        return $this->belongsTo(OrderStatus::class, 'order_status_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function originBranch()
    {
        return $this->belongsTo(Branch::class, 'origin_branch_id');
    }

    public function destinationBranch()
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
    }

    public function assignedCourier()
    {
        return $this->belongsTo(BranchEmployee::class, 'assigned_courier_id');
    }

    public function couriers()
    {
        return $this->hasManyThrough(Courier::class, CourierAssignment::class, 'order_id', 'id', 'id', 'courier_id');
    }

    public function courierAssignments()
    {
        return $this->hasMany(CourierAssignment::class, 'order_id');
    }

    public function carrierWaybills()
    {
        return $this->hasMany(CarrierWaybill::class, 'order_id');
    }

    public function latestCarrierWaybill()
    {
        return $this->hasOne(CarrierWaybill::class, 'order_id')->latestOfMany();
    }

    public function consumedCarrierCompany()
    {
        return $this->belongsTo(CarrierCompany::class, 'consumed_carrier_company_id');
    }

    public function shipmentActions()
    {
        return $this->hasMany(ShipmentAction::class, 'order_id');
    }

    public function deliverySchedules()
    {
        return $this->hasMany(DeliverySchedule::class, 'order_id');
    }

    public function userSubscription()
    {
        return $this->belongsTo(UserSubscription::class, 'user_subscription_id');
    }

    public function shipmentTracking()
    {
        return $this->hasMany(ShipmentTracking::class, 'order_id');
    }

    public function latestTracking()
    {
        return $this->hasOne(ShipmentTracking::class, 'order_id')->latestOfMany('event_time');
    }

    public function extraServices()
    {
        return $this->hasMany(OrderExtraService::class, 'order_id');
    }

    public function orderReturn()
    {
        return $this->hasOne(OrderReturn::class, 'original_order_id');
    }

    public function returnOrder()
    {
        return $this->hasOne(OrderReturn::class, 'return_order_id');
    }

    public function deliveryAttempts()
    {
        return $this->hasMany(DeliveryAttempt::class, 'order_id')->orderBy('attempt_number');
    }

    public function trips()
    {
        return $this->belongsToMany(Trip::class, 'trip_orders', 'order_id', 'trip_id')
            ->withPivot(['task_type', 'status', 'stop_sequence', 'scheduled_time', 'completed_at', 'notes'])
            ->withTimestamps();
    }

    public function tripOrders()
    {
        return $this->hasMany(TripOrder::class, 'order_id')->orderBy('stop_sequence');
    }

    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class, 'order_id');
    }

    public function courierBookings()
    {
        return $this->hasMany(CourierBooking::class, 'order_id');
    }

    public function getExtraServicesTotalAttribute(): float
    {
        return $this->extraServices->sum('total_amount');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
