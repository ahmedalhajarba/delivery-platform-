<?php

namespace App\Models;

use \DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Company extends Model implements HasMedia
{
    use SoftDeletes;
    use InteractsWithMedia;
    use HasFactory;

    public const TAX_EXEMPTION_RADIO = [
        '0' => 'No',
        '1' => 'Yes',
    ];

    public const HAVE_EN_ACCOUNT_RADIO = [
        '0' => 'No',
        '1' => 'yes',
    ];

    public $table = 'companies';

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $appends = [
        'image_cr',
        'vat',
        'proof_tax_exemption',
    ];

    protected $fillable = [
        'name_ar',
        'name_en',
        'trade_name_ar',
        'trade_name_en',
        'have_en_account',
        'country',
        'account_code',
        'account_number',
        'crn',
        'tax',
        'tax_exemption',
        'city_id',
        'street_name',
        'mobile',
        'finde_us',
        'user_id',
        'company_type_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public static function boot()
    {
        parent::boot();
        Company::observe(new \App\Observers\CompanyActionObserver());
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')->fit('crop', 50, 50);
        $this->addMediaConversion('preview')->fit('crop', 120, 120);
    }

    public function companyFinancialOfficers()
    {
        return $this->hasMany(FinancialOfficer::class, 'company_id', 'id');
    }

    public function getImageCrAttribute()
    {
        $file = $this->getMedia('image_cr')->last();
        if ($file) {
            $file->url       = $file->getUrl();
            $file->thumbnail = $file->getUrl('thumb');
            $file->preview   = $file->getUrl('preview');
        }

        return $file;
    }

    public function getVatAttribute()
    {
        return $this->getMedia('vat')->last();
    }

    public function getProofTaxExemptionAttribute()
    {
        return $this->getMedia('proof_tax_exemption')->last();
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function company_type()
    {
        return $this->belongsTo(CompanyType::class, 'company_type_id');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
