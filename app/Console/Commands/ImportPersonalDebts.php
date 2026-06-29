<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\StrategicFinance\Counterparty;
use App\Models\StrategicFinance\LegacyCompany;
use App\Models\StrategicFinance\Obligation;
use Illuminate\Support\Facades\DB;

class ImportPersonalDebts extends Command
{
    protected $signature = 'debts:import-personal {file}';
    protected $description = 'استيراد الديون الشخصية من ملف CSV وربطها بجهة اتصال ودائن (مسفر بن سعيد)';

    public function handle()
    {
        $file = $this->argument('file');
        if (!file_exists($file)) {
            $this->error("الملف غير موجود: $file");
            return 1;
        }
        $handle = fopen($file, 'r');
        if (!$handle) {
            $this->error('تعذر فتح الملف');
            return 1;
        }
        // جلب أو إنشاء شركة مفلسة باسم مسفر بن سعيد
        $ownerName = 'مسفر بن سعيد';
        $legacyCompany = LegacyCompany::firstOrCreate([
            'owner_name' => $ownerName
        ], [
            'name' => $ownerName,
            'status' => 'active',
        ]);
        $header = fgetcsv($handle);
        if (!$header) {
            $this->error('الملف فارغ أو غير صالح');
            fclose($handle);
            return 1;
        }
        // تعيين الأعمدة حسب الملف
        // مثال: [#, اسم الدائن, تاريخ الاستحقاق, رقم مرجعي, المبلغ الأصلي, المبلغ المتبقي, ملاحظات ...]
        $colMap = [
            'creditor' => 1,
            'due_date' => 2,
            'reference_code' => 3,
            'original_amount' => 4,
            'outstanding_amount' => 5,
            'notes' => 8,
        ];
        $count = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $creditor = trim($row[$colMap['creditor']] ?? '');
            $amount = floatval(str_replace(',', '', $row[$colMap['original_amount']] ?? '0'));
            if ($creditor === '' || $amount <= 0) continue;
            // جلب أو إنشاء جهة اتصال
            $counterparty = Counterparty::firstOrCreate([
                'name' => $creditor
            ], [
                'category' => 'creditor_contact',
                'sub_category' => 'دين شخصي',
                'is_active' => true,
            ]);
            // التحقق من عدم تكرار نفس الدين
            $exists = Obligation::where('counterparty_id', $counterparty->id)
                ->where('bankrupt_company_id', $legacyCompany->id)
                ->where('title', $creditor)
                ->where('original_amount', $amount)
                ->exists();
            if ($exists) continue;
            // معالجة reference_code
            $ref = trim($row[$colMap['reference_code']] ?? '');
            if ($ref === '' || $ref === '-' || Obligation::where('reference_code', $ref)->exists()) {
                $ref = uniqid('ref');
            }
            // إضافة الدين
            Obligation::create([
                'reference_code' => $ref,
                'counterparty_id' => $counterparty->id,
                'bankrupt_company_id' => $legacyCompany->id,
                'category' => 'دين شخصي',
                'title' => $creditor,
                'description' => $row[$colMap['notes']] ?? null,
                'currency_code' => 'SAR',
                'original_amount' => $amount,
                'outstanding_amount' => floatval(str_replace(',', '', $row[$colMap['outstanding_amount']] ?? $amount)),
                'priority_level' => 3,
                'status' => 'open',
                'due_date' => $this->parseDate($row[$colMap['due_date']] ?? null),
            ]);
            $count++;
        }
        fclose($handle);
        $this->info("تم استيراد $count دين شخصي بنجاح.");
        return 0;
    }

    private function parseDate($date)
    {
        if (!$date) return null;
        $date = str_replace(['\\', '/'], '-', $date);
        $parts = explode('-', $date);
        if (count($parts) === 3) {
            // day-month-year or day-month-yy
            $y = strlen($parts[2]) === 2 ? '20'.$parts[2] : $parts[2];
            return "$y-{$parts[1]}-{$parts[0]}";
        }
        return null;
    }
}
