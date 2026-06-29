<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractPricingLine extends Model
{
    public $table = 'contract_pricing_lines';

    protected $fillable = [
        'contract_id', 'service_type', 'zone_from', 'zone_to',
        'weight_unit', 'base_price', 'price_per_kg', 'free_weight_kg',
        'include_insurance', 'insurance_rate',
        'include_packaging', 'packaging_price',
        'include_vat', 'vat_rate', 'notes',
    ];

    protected $casts = [
        'include_insurance' => 'boolean',
        'include_packaging' => 'boolean',
        'include_vat'       => 'boolean',
    ];

    public function contract() { return $this->belongsTo(Contract::class); }

    public function getServiceLabelAttribute()
    {
        return QuotationPricingLine::SERVICE_TYPES[$this->service_type] ?? $this->service_type;
    }
}
