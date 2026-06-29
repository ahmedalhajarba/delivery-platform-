<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractDocument extends Model
{
    public $table = 'contract_documents';

    const DOCUMENT_TYPES = [
        'signed_contract'      => 'العقد الموقع',
        'id_copy'              => 'نسخة الهوية',
        'commercial_register'  => 'السجل التجاري',
        'tax_certificate'      => 'شهادة الضريبة',
        'bank_letter'          => 'خطاب بنكي',
        'quotation_pdf'        => 'عرض الأسعار PDF',
        'other'                => 'أخرى',
    ];

    protected $fillable = [
        'contract_id', 'quotation_id', 'activation_request_id',
        'document_type', 'file_path', 'original_name', 'uploaded_by',
    ];

    public function contract()           { return $this->belongsTo(Contract::class); }
    public function quotation()          { return $this->belongsTo(Quotation::class); }
    public function activationRequest()  { return $this->belongsTo(AccountActivationRequest::class, 'activation_request_id'); }
    public function uploadedBy()         { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function getTypeLabelAttribute() { return self::DOCUMENT_TYPES[$this->document_type] ?? $this->document_type; }
    public function getUrlAttribute() { return asset('storage/' . $this->file_path); }
}
