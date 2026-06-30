<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StrategicFinanceObligationsSeeder extends Seeder
{
    public function run()
    {
        $connection = DB::connection('strategic_finance');
        $dueDate = Carbon::create(2021, 9, 19)->toDateString();

        $bankruptCompanyName = 'ميسان للنقل والتخزين';
        $bankruptCompany = $connection->table('sf_legacy_companies')->where('name', $bankruptCompanyName)->first();

        if (!$bankruptCompany) {
            $bankruptCompanyId = $connection->table('sf_legacy_companies')->insertGetId([
                'name' => $bankruptCompanyName,
                'status' => 'bankrupt',
                'notes' => 'شركة متعثرة مرتبطة بالتزامات تاريخية.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $bankruptCompanyId = (int) $bankruptCompany->id;
        }

        $counterpartyRows = [
            ['name' => 'شركة عالم السيارات', 'category' => 'car_rental'],
            ['name' => 'شركة رأس السعودية المحدودة', 'category' => 'car_rental'],
            ['name' => 'شركة سمسا - ميسان', 'category' => 'shipping'],
            ['name' => 'شركة سمسا - حلول مياس للتخزين', 'category' => 'shipping'],
            ['name' => 'شركة ارامكس - حساب ميسان', 'category' => 'shipping'],
            ['name' => 'شركة ارامكس - حساب اعمار', 'category' => 'shipping'],
            ['name' => 'شركة ارامكس - حساب جدة', 'category' => 'shipping'],
            ['name' => 'شركة دي اتش ال', 'category' => 'shipping'],
            ['name' => 'البريد السعودي', 'category' => 'shipping'],
            ['name' => 'شركة أوفر', 'category' => 'shipping'],
            ['name' => 'شركة المجدوعي', 'category' => 'shipping'],
            ['name' => 'منصة الخبراء للتقنية - عبدالرحمن الشهري', 'category' => 'technology'],
            ['name' => 'مكتب جدة', 'category' => 'office_rental'],
            ['name' => 'مكتب الرياض', 'category' => 'office_rental'],
            ['name' => 'مكتب الدمام', 'category' => 'office_rental'],
            ['name' => 'فرع جدة القديم', 'category' => 'office_rental'],
            ['name' => 'مكتب الجبيل', 'category' => 'office_rental'],
        ];

        $counterpartyIds = [];

        foreach ($counterpartyRows as $row) {
            $existing = $connection->table('sf_counterparties')->where('name', $row['name'])->first();

            if ($existing) {
                $counterpartyIds[$row['name']] = (int) $existing->id;
                continue;
            }

            $id = $connection->table('sf_counterparties')->insertGetId([
                'name' => $row['name'],
                'category' => $row['category'],
                'contact_person' => null,
                'contact_phone' => null,
                'contact_email' => null,
                'notes' => 'التزام سابق مرتبط بالشركة المتعثرة: ' . $bankruptCompanyName,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $counterpartyIds[$row['name']] = (int) $id;
        }

        $obligations = [
            ['company' => 'شركة عالم السيارات', 'classification' => 'شركة تأجير سيارات', 'amount' => 190029.15, 'review_note' => 'بحاجة لمراجعة'],
            ['company' => 'شركة رأس السعودية المحدودة', 'classification' => 'شركة تأجير سيارات', 'amount' => 174003.18, 'review_note' => 'بحاجة لمراجعة'],
            ['company' => 'شركة سمسا - ميسان', 'classification' => 'شركة شحن', 'amount' => 178433.30, 'review_note' => null],
            ['company' => 'شركة سمسا - حلول مياس للتخزين', 'classification' => 'شركة شحن', 'amount' => 10000.00, 'review_note' => 'بحاجة لمراجعة'],
            ['company' => 'شركة ارامكس - حساب ميسان', 'classification' => 'شركة شحن', 'amount' => 158210.96, 'review_note' => 'بحاجة لمراجعة'],
            ['company' => 'شركة ارامكس - حساب اعمار', 'classification' => 'شركة شحن', 'amount' => 47670.34, 'review_note' => 'بحاجة لمراجعة'],
            ['company' => 'شركة ارامكس - حساب جدة', 'classification' => 'شركة شحن', 'amount' => 141143.89, 'review_note' => 'بحاجة لمراجعة'],
            ['company' => 'شركة دي اتش ال', 'classification' => 'شركة شحن', 'amount' => 111170.82, 'review_note' => null],
            ['company' => 'البريد السعودي', 'classification' => 'شركة شحن', 'amount' => 50879.45, 'review_note' => null],
            ['company' => 'شركة أوفر', 'classification' => 'شركة شحن', 'amount' => 0.00, 'review_note' => null],
            ['company' => 'شركة المجدوعي', 'classification' => 'شركة شحن', 'amount' => 41003.25, 'review_note' => null],
            ['company' => 'منصة الخبراء للتقنية - عبدالرحمن الشهري', 'classification' => 'شركة تقنية', 'amount' => 80000.00, 'review_note' => null],
            ['company' => 'مكتب جدة', 'classification' => 'شركة تأجير مكاتب', 'amount' => 5000.00, 'review_note' => null],
            ['company' => 'مكتب الرياض', 'classification' => 'شركة تأجير مكاتب', 'amount' => 5000.00, 'review_note' => null],
            ['company' => 'مكتب الدمام', 'classification' => 'شركة تأجير مكاتب', 'amount' => 5000.00, 'review_note' => null],
            ['company' => 'فرع جدة القديم', 'classification' => 'شركة تأجير مكاتب', 'amount' => 5000.00, 'review_note' => null],
            ['company' => 'مكتب الجبيل', 'classification' => 'شركة تأجير مكاتب', 'amount' => 15000.00, 'review_note' => null],
        ];

        foreach ($obligations as $index => $row) {
            $referenceCode = sprintf('MZN-20210919-%03d', $index + 1);

            $existing = $connection->table('sf_obligations')->where('reference_code', $referenceCode)->first();

            $payload = [
                'counterparty_id' => $counterpartyIds[$row['company']] ?? null,
                'bankrupt_company_id' => $bankruptCompanyId,
                'category' => $row['classification'],
                'title' => 'التزام مستحق - ' . $row['company'],
                'description' => trim('الشركة المفلسة: ' . $bankruptCompanyName . ' | ' . ($row['review_note'] ?? '')),
                'currency_code' => 'SAR',
                'original_amount' => $row['amount'],
                'outstanding_amount' => $row['amount'],
                'priority_level' => ($row['review_note'] ? 2 : 3),
                'status' => $row['amount'] <= 0 ? 'closed' : 'open',
                'due_date' => $dueDate,
                'scheduled_start_date' => $dueDate,
                'scheduled_end_date' => $dueDate,
                'updated_at' => now(),
            ];

            if ($existing) {
                $connection->table('sf_obligations')->where('id', $existing->id)->update($payload);
                continue;
            }

            $payload['reference_code'] = $referenceCode;
            $payload['created_at'] = now();

            $connection->table('sf_obligations')->insert($payload);
        }
    }
}
