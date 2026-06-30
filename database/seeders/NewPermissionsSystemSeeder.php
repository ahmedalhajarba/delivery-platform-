<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NewPermissionsSystemSeeder extends Seeder
{
    public function run()
    {
        // الأقسام
        $sections = [
            ['name' => 'orders', 'label' => 'الطلبات'],
            ['name' => 'branches', 'label' => 'الفروع'],
            ['name' => 'couriers', 'label' => 'المناديب'],
            ['name' => 'employees', 'label' => 'الموظفين'],
            ['name' => 'finance', 'label' => 'المالية'],
            ['name' => 'settings', 'label' => 'الإعدادات العامة'],
            ['name' => 'users', 'label' => 'المستخدمين'],
        ];
        DB::table('permission_sections')->insert($sections);

        // جلب الأقسام بعد الإدخال
        $sectionsDb = DB::table('permission_sections')->get();

        // العمليات القياسية
        $actions = [
            'access' => 'الدخول',
            'show' => 'عرض',
            'create' => 'إضافة',
            'edit' => 'تعديل',
            'delete' => 'حذف',
            'print' => 'طباعة',
        ];

        // الصلاحيات
        $permissions = [];
        foreach ($sectionsDb as $section) {
            foreach ($actions as $action => $label) {
                $permissions[] = [
                    'section_id' => $section->id,
                    'action' => $action,
                    'label' => $label . ' ' . $section->label,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        DB::table('permissions')->insert($permissions);

        // أدوار افتراضية
        $roles = [
            ['name' => 'admin', 'label' => 'مدير النظام'],
            ['name' => 'manager', 'label' => 'مدير فرع'],
            ['name' => 'employee', 'label' => 'موظف'],
        ];
        DB::table('roles')->insert($roles);
    }
}
