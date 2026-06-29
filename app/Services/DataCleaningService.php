<?php
namespace App\Services;

class DataCleaningService
{
    /**
     * ينظف ويحلل تفاصيل العملية المالية
     */
    public function cleanDescription(string $description): array
    {
        // إزالة الرموز غير المهمة
        $desc = preg_replace('/[^\p{L}\p{N} ]+/u', ' ', $description);
        // توحيد النصوص
        $desc = mb_strtolower(trim(preg_replace('/\s+/', ' ', $desc)));

        // استخراج الكلمات المفتاحية
        $keywords = $this->extractKeywords($desc);
        // تحديد نوع العملية
        $operationType = $this->detectOperationType($keywords, $desc);
        // استخراج الدولة
        $country = $this->extractCountry($desc);
        // تحديد الاشتباه
        $riskFlag = $this->detectRisk($desc, $keywords, $operationType);

        return [
            'cleaned_description' => $desc,
            'keywords' => $keywords,
            'operation_type' => $operationType,
            'country' => $country,
            'risk_flag' => $riskFlag,
        ];
    }

    private function extractKeywords(string $desc): array
    {
        $words = ['تحويل', 'رسوم', 'حوالة', 'ايداع', 'سحب', 'دولي', 'محلي', 'امارات', 'سعودية', 'قطر', 'مصر', 'دفع', 'شراء', 'atm', 'cash', 'fee', 'transfer', 'deposit', 'withdraw', 'external'];
        $found = [];
        foreach ($words as $w) {
            if (mb_strpos($desc, $w) !== false) {
                $found[] = $w;
            }
        }
        return $found;
    }

    private function detectOperationType(array $keywords, string $desc): string
    {
        if (in_array('تحويل', $keywords) || in_array('transfer', $keywords)) {
            if (in_array('دولي', $keywords) || in_array('external', $keywords)) {
                return 'External Transfer';
            }
            return 'Transfer';
        }
        if (in_array('رسوم', $keywords) || in_array('fee', $keywords)) {
            return 'Fee';
        }
        if (in_array('ايداع', $keywords) || in_array('deposit', $keywords)) {
            return 'Deposit';
        }
        if (in_array('سحب', $keywords) || in_array('withdraw', $keywords) || in_array('atm', $keywords) || in_array('cash', $keywords)) {
            return 'Cash';
        }
        return 'Unknown';
    }

    private function extractCountry(string $desc): ?string
    {
        $countries = ['الامارات' => 'UAE', 'سعودية' => 'KSA', 'قطر' => 'Qatar', 'مصر' => 'Egypt'];
        foreach ($countries as $ar => $en) {
            if (mb_strpos($desc, $ar) !== false) {
                return $en;
            }
        }
        return null;
    }

    private function detectRisk(string $desc, array $keywords, string $operationType): bool
    {
        // أمثلة على الاشتباه: تحويل دولي، رسوم متكررة، كلمات مشبوهة
        $suspicious = ['غسيل', 'مشبوه', 'مجهول', 'غير معروف', 'fraud', 'suspicious', 'unknown'];
        foreach ($suspicious as $w) {
            if (mb_strpos($desc, $w) !== false) {
                return true;
            }
        }
        if ($operationType === 'External Transfer') {
            return true;
        }
        return false;
    }
}
