<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use SoftDeletes;

    public $table = 'contracts';

    const STATUS_LABELS = [
        'draft'            => 'مسودة',
        'pending_approval' => 'بانتظار الموافقة',
        'active'           => 'ساري',
        'suspended'        => 'موقوف',
        'terminated'       => 'منهي',
        'expired'          => 'منتهي',
    ];

    const STATUS_COLORS = [
        'draft'            => 'secondary',
        'pending_approval' => 'warning',
        'active'           => 'success',
        'suspended'        => 'orange',
        'terminated'       => 'danger',
        'expired'          => 'dark',
    ];

    protected $fillable = [
        'contract_number', 'user_id', 'quotation_id', 'created_by', 'approved_by',
        'status', 'start_date', 'end_date', 'auto_renew', 'renewal_notice_days',
        'deferred_payment_enabled', 'deferred_days', 'credit_limit', 'discount_percent',
        'custom_order_settings', 'max_orders_per_day', 'max_weight_per_order',
        'max_packages_per_order', 'allow_cod', 'max_cod_amount',
        'allow_international', 'allow_express', 'allow_storage',
        'terms_and_conditions', 'internal_notes', 'signed_document',
    ];

    protected $casts = [
        'start_date'               => 'date',
        'end_date'                 => 'date',
        'auto_renew'               => 'boolean',
        'deferred_payment_enabled' => 'boolean',
        'custom_order_settings'    => 'boolean',
        'allow_cod'                => 'boolean',
        'allow_international'      => 'boolean',
        'allow_express'            => 'boolean',
        'allow_storage'            => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->contract_number)) {
                $model->contract_number = 'CT-' . date('Y') . '-' . str_pad(
                    (Contract::whereYear('created_at', date('Y'))->count() + 1), 5, '0', STR_PAD_LEFT
                );
            }
        });
    }

    public function user()         { return $this->belongsTo(User::class, 'user_id'); }
    public function quotation()    { return $this->belongsTo(Quotation::class); }
    public function createdBy()    { return $this->belongsTo(User::class, 'created_by'); }
    public function approvedBy()   { return $this->belongsTo(User::class, 'approved_by'); }
    public function pricingLines() { return $this->hasMany(ContractPricingLine::class); }
    public function extraServices(){ return $this->hasMany(ContractExtraService::class); }
    public function approvals()    { return $this->hasMany(ContractApproval::class); }
    public function documents()    { return $this->hasMany(ContractDocument::class); }
    public function customerSettings() { return $this->hasOne(CustomerOrderSetting::class); }
    public function activationRequest() { return $this->hasOne(AccountActivationRequest::class); }

    public function getStatusLabelAttribute() { return self::STATUS_LABELS[$this->status] ?? $this->status; }
    public function getStatusColorAttribute() { return self::STATUS_COLORS[$this->status] ?? 'secondary'; }
    public function isActive() { return $this->status === 'active'; }
    public function isExpired() { return $this->end_date && $this->end_date->isPast(); }

    public function getPricingForService(string $serviceType, ?string $zoneFrom = null, ?string $zoneTo = null)
    {
        return $this->pricingLines()
            ->where('service_type', $serviceType)
            ->when($zoneFrom, fn($q) => $q->where('zone_from', $zoneFrom))
            ->when($zoneTo,   fn($q) => $q->where('zone_to',   $zoneTo))
            ->first();
    }
}
