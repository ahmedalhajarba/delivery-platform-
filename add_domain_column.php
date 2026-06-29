<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    // إضافة حقل domain
    if (!DB::connection()->getSchemaBuilder()->hasColumn('permission_sections', 'domain')) {
        DB::statement('ALTER TABLE permission_sections ADD COLUMN domain VARCHAR(255) NULL AFTER label');
        echo "✓ تم إضافة حقل domain بنجاح\n";
    } else {
        echo "✓ الحقل domain موجود بالفعل\n";
    }
} catch (\Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
}
