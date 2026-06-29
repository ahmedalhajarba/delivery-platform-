<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;


class ImportBankStatement extends Command
{
    protected $signature = 'bank:import {csv_path} {--report=} {--export=}';
    protected $description = 'استيراد وتحليل كشف حساب بنك شركة الحلول من ملف CSV مع تقارير متقدمة.';

    public function handle()
    {
        $csvPath = $this->argument('csv_path');
        $reportType = $this->option('report') ?: 'summary';
        $exportPath = $this->option('export');

        if (!file_exists($csvPath)) {
            $this->error('الملف غير موجود: ' . $csvPath);
            return 1;
        }

        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            $this->error('تعذر فتح الملف');
            return 1;
        }

        $header = fgetcsv($handle);
        $count = 0;
        while (($row = fgetcsv($handle)) !== false) {
            [$raw_balance, $raw_debit, $raw_credit, $operation_details, $beneficiary_name, $operation_date] = $row;
            $balance = self::parseNumber($raw_balance);
            $debit = self::parseNumber($raw_debit);
            $credit = self::parseNumber($raw_credit);
            $date = self::parseDate($operation_date);
            DB::table('bank_statement')->insert([
                'balance' => $balance,
                'debit' => $debit,
                'credit' => $credit,
                'operation_details' => $operation_details,
                'beneficiary_name' => $beneficiary_name,
                'operation_date' => $date,
                'raw_balance' => $raw_balance,
                'raw_debit' => $raw_debit,
                'raw_credit' => $raw_credit,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $count++;
        }
        fclose($handle);
        $this->info("تم استيراد {$count} حركة بنكية.");

        // التقارير المتقدمة
        switch ($reportType) {
            case 'summary':
                $this->reportSummary($exportPath);
                break;
            case 'detailed':
                $this->reportDetailed($exportPath);
                break;
            case 'monthly':
                $this->reportMonthly($exportPath);
                break;
            case 'top-beneficiaries':
                $this->reportTopBeneficiaries($exportPath);
                break;
            case 'unknown':
                $this->reportUnknown($exportPath);
                break;
            case 'by-type':
                $this->reportByType($exportPath);
                break;
            default:
                $this->reportSummary($exportPath);
        }
        return 0;
    }

    private function reportSummary($exportPath = null)
    {
        $this->info("\n--- تقرير إجمالي لكل مستفيد ---");
        $results = DB::table('bank_statement')
            ->select('beneficiary_name',
                DB::raw('SUM(CASE WHEN debit < 0 THEN -debit ELSE 0 END) AS total_withdrawn'),
                DB::raw('SUM(CASE WHEN credit > 0 THEN credit ELSE 0 END) AS total_deposited'),
                DB::raw('COUNT(*) AS transaction_count')
            )
            ->whereNotNull('beneficiary_name')
            ->where('beneficiary_name', '!=', '')
            ->groupBy('beneficiary_name')
            ->orderByDesc('total_withdrawn')
            ->orderByDesc('total_deposited')
            ->get();
        $lines = [];
        foreach ($results as $row) {
            $line = "{$row->beneficiary_name}: سحب = {$row->total_withdrawn} | إيداع = {$row->total_deposited} | عدد العمليات = {$row->transaction_count}";
            $this->line($line);
            $lines[] = [$row->beneficiary_name, $row->total_withdrawn, $row->total_deposited, $row->transaction_count];
        }
        if ($exportPath) {
            $this->exportCsv($exportPath, ['beneficiary_name', 'total_withdrawn', 'total_deposited', 'transaction_count'], $lines);
        }
    }

    private function reportDetailed($exportPath = null)
    {
        $this->info("\n--- كشف تفصيلي لكل عملية ---");
        $results = DB::table('bank_statement')
            ->select('operation_date', 'operation_details', 'beneficiary_name', 'debit', 'credit', 'balance',
                DB::raw("CASE WHEN debit < 0 THEN 'سحب' WHEN credit > 0 THEN 'إيداع' ELSE 'أخرى' END AS type"))
            ->orderByDesc('operation_date')
            ->get();
        $lines = [];
        foreach ($results as $row) {
            $line = "{$row->operation_date} | {$row->operation_details} | {$row->beneficiary_name} | {$row->debit} | {$row->credit} | {$row->balance} | {$row->type}";
            $this->line($line);
            $lines[] = [$row->operation_date, $row->operation_details, $row->beneficiary_name, $row->debit, $row->credit, $row->balance, $row->type];
        }
        if ($exportPath) {
            $this->exportCsv($exportPath, ['operation_date', 'operation_details', 'beneficiary_name', 'debit', 'credit', 'balance', 'type'], $lines);
        }
    }

    private function reportMonthly($exportPath = null)
    {
        $this->info("\n--- كشف شهري إجمالي ---");
        $results = DB::table('bank_statement')
            ->select(DB::raw('DATE_FORMAT(operation_date, "%Y-%m") as month'),
                DB::raw('SUM(CASE WHEN debit < 0 THEN -debit ELSE 0 END) AS total_withdrawn'),
                DB::raw('SUM(CASE WHEN credit > 0 THEN credit ELSE 0 END) AS total_deposited'),
                DB::raw('COUNT(*) AS transaction_count'))
            ->groupBy(DB::raw('DATE_FORMAT(operation_date, "%Y-%m")'))
            ->orderBy('month')
            ->get();
        $lines = [];
        foreach ($results as $row) {
            $line = "{$row->month}: سحب = {$row->total_withdrawn} | إيداع = {$row->total_deposited} | عدد العمليات = {$row->transaction_count}";
            $this->line($line);
            $lines[] = [$row->month, $row->total_withdrawn, $row->total_deposited, $row->transaction_count];
        }
        if ($exportPath) {
            $this->exportCsv($exportPath, ['month', 'total_withdrawn', 'total_deposited', 'transaction_count'], $lines);
        }
    }

    private function reportTopBeneficiaries($exportPath = null)
    {
        $this->info("\n--- أكثر المستفيدين سحباً ---");
        $results = DB::table('bank_statement')
            ->select('beneficiary_name',
                DB::raw('SUM(CASE WHEN debit < 0 THEN -debit ELSE 0 END) AS total_withdrawn'),
                DB::raw('SUM(CASE WHEN credit > 0 THEN credit ELSE 0 END) AS total_deposited'))
            ->whereNotNull('beneficiary_name')
            ->where('beneficiary_name', '!=', '')
            ->groupBy('beneficiary_name')
            ->orderByDesc('total_withdrawn')
            ->limit(10)
            ->get();
        foreach ($results as $row) {
            $this->line("{$row->beneficiary_name}: سحب = {$row->total_withdrawn} | إيداع = {$row->total_deposited}");
        }
    }

    private function reportUnknown($exportPath = null)
    {
        $this->info("\n--- العمليات المجهولة (بدون اسم مستفيد) ---");
        $results = DB::table('bank_statement')
            ->whereNull('beneficiary_name')
            ->orWhere('beneficiary_name', '')
            ->orderByDesc('operation_date')
            ->get();
        foreach ($results as $row) {
            $this->line("{$row->operation_date} | {$row->operation_details} | {$row->debit} | {$row->credit}");
        }
    }

    private function reportByType($exportPath = null)
    {
        $this->info("\n--- كشف العمليات حسب نوع العملية ---");
        $results = DB::table('bank_statement')
            ->select('operation_details',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(CASE WHEN debit < 0 THEN -debit ELSE 0 END) AS total_withdrawn'),
                DB::raw('SUM(CASE WHEN credit > 0 THEN credit ELSE 0 END) AS total_deposited'))
            ->groupBy('operation_details')
            ->orderByDesc('count')
            ->limit(20)
            ->get();
        foreach ($results as $row) {
            $this->line("{$row->operation_details}: عدد = {$row->count} | سحب = {$row->total_withdrawn} | إيداع = {$row->total_deposited}");
        }
    }

    private function exportCsv($path, $headers, $rows)
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        $this->info("تم تصدير التقرير إلى: $path");
    }

    private static function parseNumber($value)
    {
        $value = str_replace([',', '"'], '', $value);
        return is_numeric($value) ? (float)$value : null;
    }

    private static function parseDate($value)
    {
        if (!$value) return null;
        $date = Carbon::createFromFormat('d/m/Y', trim($value));
        return $date ? $date->format('Y-m-d') : null;
    }
}
