<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationExtraService extends Model
{
    public $table = 'quotation_extra_services';

    protected $fillable = [
        'quotation_id',
        'service_name',
        'description',
        'price',
        'price_type',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }
}
