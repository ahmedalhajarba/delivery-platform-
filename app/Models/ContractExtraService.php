<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractExtraService extends Model
{
    public $table = 'contract_extra_services';
    protected $fillable = ['contract_id', 'service_name', 'description', 'price', 'price_type'];
    public function contract() { return $this->belongsTo(Contract::class); }
}
