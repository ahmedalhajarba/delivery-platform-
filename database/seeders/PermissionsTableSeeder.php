<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionsTableSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['section' => 'user', 'action_key' => 'access', 'title' => 'الدخول للمستخدمين'],
            ['section' => 'user', 'action_key' => 'show', 'title' => 'عرض المستخدمين'],
            ['section' => 'user', 'action_key' => 'create', 'title' => 'إضافة مستخدم'],
            ['section' => 'user', 'action_key' => 'edit', 'title' => 'تعديل مستخدم'],
            ['section' => 'user', 'action_key' => 'delete', 'title' => 'حذف مستخدم'],

            ['section' => 'role', 'action_key' => 'access', 'title' => 'الدخول للأدوار'],
            ['section' => 'role', 'action_key' => 'show', 'title' => 'عرض الأدوار'],
            ['section' => 'role', 'action_key' => 'create', 'title' => 'إضافة دور'],
            ['section' => 'role', 'action_key' => 'edit', 'title' => 'تعديل دور'],
            ['section' => 'role', 'action_key' => 'delete', 'title' => 'حذف دور'],

            ['section' => 'permission', 'action_key' => 'access', 'title' => 'الدخول للصلاحيات'],
            ['section' => 'permission', 'action_key' => 'show', 'title' => 'عرض الصلاحيات'],
            ['section' => 'permission', 'action_key' => 'create', 'title' => 'إضافة صلاحية'],
            ['section' => 'permission', 'action_key' => 'edit', 'title' => 'تعديل صلاحية'],
            ['section' => 'permission', 'action_key' => 'delete', 'title' => 'حذف صلاحية'],
        ];

        foreach ($rows as $row) {
            Permission::query()->updateOrCreate(
                [
                    'section' => $row['section'],
                    'action_key' => $row['action_key'],
                ],
                [
                    'title' => $row['title'],
                    'action' => $row['action_key'],
                    'description' => null,
                ]
            );
        }
    }
}
