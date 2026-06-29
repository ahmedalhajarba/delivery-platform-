<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MboxImportService;

class ImportMbox extends Command
{
    protected $signature = 'email:import {mbox_path} {email_account_id}';
    protected $description = 'استيراد رسائل بريدية من ملف mbox كبير الحجم';

    public function handle(MboxImportService $importService)
    {
        $mboxPath = $this->argument('mbox_path');
        $accountId = $this->argument('email_account_id');
        if (!file_exists($mboxPath)) {
            $this->error('الملف غير موجود: ' . $mboxPath);
            return 1;
        }
        $this->info('بدء الاستيراد...');
        $count = $importService->importFromMbox($mboxPath, $accountId);
        $this->info("تم استيراد {$count} رسالة بنجاح.");
        return 0;
    }
}
