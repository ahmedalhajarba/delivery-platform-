<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$columns = DB::select("DESCRIBE permissions");
echo "أعمدة جدول permissions:\n";
foreach ($columns as $col) {
    echo "- {$col->Field}\n";
}
