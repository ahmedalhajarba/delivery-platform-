<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarrierWaybill extends Model
{
    use HasFactory, SoftDeletes;

    public $table = 'carrier_waybills';

    const INVOICE_MATCH_STATUS = [
        'pending'     => 'في الانتظار',
        'matched'     => 'متطابق',
        'discrepancy' => 'فروقات',
        'approved'    => 'معتمد',
    ];

    const STATUS = [
        'active'      => 'نشط',
        'transferred' => 'محول',
        'returned'    => 'مرتجع',
        'cancelled'   => 'ملغي',
    ];

    protected $fillable = [
        'order_id', 'carrier_company_id', 'carrier_waybill_number',
        'our_reference_number', 'carrier_cost', 'declared_cost',
        'invoiced_cost', 'invoice_match_status', 'carrier_invoice_number',
        'carrier_invoice_date', 'label_url', 'api_response', 'status',
        'notes', 'created_by',
    ];

    protected $casts = [
        'carrier_cost'        => 'decimal:2',
        'declared_cost'       => 'decimal:2',
        'invoiced_cost'       => 'decimal:2',
        'carrier_invoice_date' => 'date',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function carrierCompany()
    {
        return $this->belongsTo(CarrierCompany::class, 'carrier_company_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // الفرق بين التكلفة المعلنة والفعلية
    public function getCostDiscrepancyAttribute(): float
    {
        return (float) ($this->invoiced_cost - $this->declared_cost);
    }
}
