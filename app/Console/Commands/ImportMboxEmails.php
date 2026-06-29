<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MboxImportService;
use App\Models\EmailAccount;

class ImportMboxEmails extends Command
{
    protected $signature = 'email:import-mbox {mbox_path} {email_account_id}';
    protected $description = 'استيراد رسائل البريد من ملف mbox إلى قاعدة البيانات';

    public function handle(MboxImportService $importService)
    {
        $mboxPath = $this->argument('mbox_path');
        $accountId = $this->argument('email_account_id');
        if (!EmailAccount::find($accountId)) {
            $this->error('Email account not found.');
            return 1;
        }
        try {
            $count = $importService->importFromMbox($mboxPath, $accountId);
            $this->info("تم استيراد {$count} رسالة بنجاح.");
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }
        return 0;
    }
}
