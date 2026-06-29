<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class FixAdminRole extends Command
{
    protected $signature = 'fix:admin-role {email=admin@admin.com}';
    protected $description = 'تعيين دور الأدمن للمستخدم المطلوب وإزالة بقية الأدوار';

    public function handle()
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->error('لم يتم العثور على المستخدم: ' . $email);
            return 1;
        }
        $user->roles()->sync([1]);
        $this->info('تم تعيين دور الأدمن للمستخدم: ' . $email);
        return 0;
    }
}
