<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PermissionSection;
use App\Models\Permission;
use App\Models\Role;

// الحصول على جميع الأقسام مع صلاحياتها
$sections = PermissionSection::with('permissions')->orderBy('id')->get();

$report = <<<EOD
╔════════════════════════════════════════════════════════════════════════════════════════╗
║               تقرير شامل لنظام الصلاحيات - فحص كامل المنصة الإدارية                    ║
╚════════════════════════════════════════════════════════════════════════════════════════╝

تم إنشاء: 78 قسم صلاحيات | 382 صلاحية | 7 أدوار مسبقة

EOD;

$mainSections = [];

echo $report;
echo "\n══════════════════════════════════════════════════════════════════════════════════════\n";
echo "الأقسام والصلاحيات المُنشأة:\n";
echo "══════════════════════════════════════════════════════════════════════════════════════\n\n";

$groupedSections = [
    '1. المستخدمون والصلاحيات' => [1, 2, 3, 4],
    '2. إدارة الزبائن' => [5],
    '3. المبيعات والتسويق' => [6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18],
    '4. الطلبات' => [19, 20, 21, 22, 23, 24, 25, 26, 27],
    '5. المناديب والأسطول' => [28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39],
    '6. نظام الاشتراكات' => [40, 41, 42, 43, 44, 45, 46],
    '7. صفحات المحتوى' => [47, 48, 49],
    '8. الفروع' => [50, 51, 52, 53, 54, 55, 56, 57],
    '9. الإعدادات' => [58, 59, 60, 61],
    '10. HR والحوكمة' => [62, 63, 64, 65],
    '11. المالية' => [66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77],
    '12. النظام' => [78],
];

$totalPermissions = 0;

foreach ($groupedSections as $groupName => $sectionIds) {
    echo "\n╔════ $groupName ════╗\n";
    
    foreach ($sectionIds as $sectionId) {
        $section = $sections->where('id', $sectionId)->first();
        if ($section) {
            $permCount = $section->permissions->count();
            $totalPermissions += $permCount;
            echo "\n  📌 {$section->label}\n";
            echo "     القسم: {$section->name} | الصلاحيات: {$permCount}\n";
            echo "     الصلاحيات:\n";
            
            foreach ($section->permissions as $perm) {
                echo "        ✓ {$perm->label}\n";
            }
        }
    }
}

// عرض معلومات الأدوار
echo "\n\n══════════════════════════════════════════════════════════════════════════════════════\n";
echo "الأدوار المسبقة المُنشأة:\n";
echo "══════════════════════════════════════════════════════════════════════════════════════\n\n";

$roles = Role::where('is_system', true)->orderBy('name')->get();

foreach ($roles as $role) {
    $permCount = $role->permissions->count();
    echo "👤 {$role->label} (نظامي)\n";
    echo "   الاسم: {$role->name} | عدد الصلاحيات: {$permCount}\n\n";
}

echo "══════════════════════════════════════════════════════════════════════════════════════\n";
echo "الإجمالي: {$totalPermissions} صلاحية موزعة على 78 قسم\n";
echo "══════════════════════════════════════════════════════════════════════════════════════\n";
