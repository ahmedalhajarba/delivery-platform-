<?php
namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class TransactionAnalysisService
{
    public function analyze(Transaction $transaction): array
    {
        $score = 0;
        $rules = [];
        // قاعدة: تحويل دولي = خطورة عالية
        if ($transaction->operation_type === 'External Transfer') {
            $score += 60;
            $rules[] = 'تحويل دولي';
        }
        // قاعدة: تكرار العملية أكثر من 3 مرات في أسبوع
        $count = Transaction::where('beneficiary_name', $transaction->beneficiary_name)
            ->where('operation_type', $transaction->operation_type)
            ->whereBetween('date', [now()->subDays(7), now()])
            ->count();
        if ($count > 3) {
            $score += 20;
            $rules[] = 'تكرار العملية أكثر من 3 مرات في أسبوع';
        }
        // قاعدة: مبلغ كبير وغير مبرر
        if ($transaction->debit > 100000 || $transaction->credit > 100000) {
            $score += 30;
            $rules[] = 'مبلغ كبير';
        }
        // إذا تم وضع risk_flag من التنظيف
        if ($transaction->risk_flag) {
            $score += 20;
            $rules[] = 'اشتباه في النص';
        }
        // ضبط الحد الأعلى
        $score = min($score, 100);
        $transaction->risk_score = $score;
        $transaction->save();
        return [
            'score' => $score,
            'rules' => $rules,
        ];
    }
}
