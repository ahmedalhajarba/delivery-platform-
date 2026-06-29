<?php
// script: fix_admin_role.php
// اجعل المستخدم admin@admin.com أدمن فقط

use App\Models\User;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$user = User::where('email', 'admin@admin.com')->first();
if ($user) {
    $user->roles()->sync([1]);
    echo "تم تحديث أدوار المستخدم admin@admin.com بنجاح.\n";
} else {
    echo "لم يتم العثور على المستخدم admin@admin.com.\n";
}
