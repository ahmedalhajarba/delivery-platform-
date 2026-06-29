<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodSetting extends Model
{
    public $table = 'cod_settings';

    protected $fillable = [
        'collection_fee_rate',
        'collection_fee_fixed',
        'bank_fee_rate',
        'bank_fee_fixed',
        'payout_days',
        'min_payout_amount',
    ];

    protected $casts = [
        'collection_fee_rate'  => 'decimal:4',
        'collection_fee_fixed' => 'decimal:2',
        'bank_fee_rate'        => 'decimal:4',
        'bank_fee_fixed'       => 'decimal:2',
    ];

    // إرجاع السجل الوحيد أو إنشاؤه
    public static function instance(): self
    {
        return static::firstOrCreate([], [
            'collection_fee_rate'  => 1.5,
            'collection_fee_fixed' => 0,
            'bank_fee_rate'        => 0,
            'bank_fee_fixed'       => 5,
            'payout_days'          => '2,5',
            'min_payout_amount'    => 100,
        ]);
    }

    /**
     * احسب رسوم التحصيل لمبلغ COD معين
     */
    public function calcCollectionFee(float $codAmount): float
    {
        $fee = ($codAmount * $this->collection_fee_rate / 100) + $this->collection_fee_fixed;
        return round($fee, 2);
    }

    /**
     * احسب الرسوم البنكية على مبلغ التحويل الإجمالي
     */
    public function calcBankFee(float $transferAmount): float
    {
        $fee = ($transferAmount * $this->bank_fee_rate / 100) + $this->bank_fee_fixed;
        return round($fee, 2);
    }

    /**
     * أيام الصرف كمصفوفة (1=الأحد ... 7=السبت)
     */
    public function getPayoutDaysArray(): array
    {
        return array_filter(array_map('intval', explode(',', $this->payout_days)));
    }

    public static function dayName(int $n): string
    {
        return [1=>'الأحد',2=>'الاثنين',3=>'الثلاثاء',4=>'الأربعاء',5=>'الخميس',6=>'الجمعة',7=>'السبت'][$n] ?? '';
    }
}
