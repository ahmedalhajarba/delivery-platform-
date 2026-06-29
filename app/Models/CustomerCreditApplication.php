<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerCreditApplication extends Model
{
    use HasFactory, SoftDeletes;

    public $table = 'customer_credit_applications';

    protected $fillable = [
        'user_id',
        'customer_profile_id',
        'application_number',
        'company_name_ar',
        'company_name_en',
        'trade_name_ar',
        'trade_name_en',
        'commercial_register',
        'tax_number',
        'legal_form_ar',
        'legal_form_en',
        'business_activity_ar',
        'business_activity_en',
        'established_date',
        'head_office_address_ar',
        'head_office_address_en',
        'city_ar',
        'city_en',
        'country_ar',
        'country_en',
        'contact_person_name_ar',
        'contact_person_name_en',
        'contact_person_title_ar',
        'contact_person_title_en',
        'contact_person_phone',
        'contact_person_email',
        'finance_contact_name_ar',
        'finance_contact_name_en',
        'finance_contact_phone',
        'finance_contact_email',
        'requested_credit_limit',
        'requested_payment_cycle_days',
        'bank_name',
        'iban',
        'bank_account_name',
        'notes',
        'employee_accounts',
        'signatory_name_ar',
        'signatory_name_en',
        'signed_at',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'established_date' => 'date',
        'signed_at' => 'date',
        'requested_credit_limit' => 'decimal:2',
        'employee_accounts' => 'array',
    ];

    public static function generateNumber(): string
    {
        $prefix = 'CAF-' . now()->format('Ym') . '-';
        $last = static::withTrashed()
            ->where('application_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->first();

        $sequence = $last
            ? ((int) substr($last->application_number, strlen($prefix)) + 1)
            : 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function profile()
    {
        return $this->belongsTo(CustomerProfile::class, 'customer_profile_id');
    }
}
