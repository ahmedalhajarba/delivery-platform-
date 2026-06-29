<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationApproval extends Model
{
    public $table = 'quotation_approvals';

    protected $fillable = ['quotation_id', 'user_id', 'action', 'comment'];

    public function quotation() { return $this->belongsTo(Quotation::class); }
    public function user()      { return $this->belongsTo(User::class); }
}
