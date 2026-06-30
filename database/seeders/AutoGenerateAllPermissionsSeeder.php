<?php

namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class AutoGenerateAllPermissionsSeeder extends Seeder
{
    public function run()
    {
        // 1. Scan all Admin controllers
        $controllerPath = app_path('Http/Controllers/Admin');
        $controllers = File::allFiles($controllerPath);

        // 2. Define standard actions
        $actions = [
            'access' => 'الدخول',
            'show' => 'عرض',
            'create' => 'إضافة',
            'edit' => 'تعديل',
            'delete' => 'حذف',
            'print' => 'طباعة',
        ];

        $sections = [];
        $permissions = [];
        $now = now();

        foreach ($controllers as $file) {
            $className = $file->getFilenameWithoutExtension();
            $sectionName = Str::snake(str_replace('Controller', '', $className));
            $sectionLabel = ucwords(str_replace(['Controller', '_'], ['', ' '], $className));

            // Insert or get section
            $section = DB::table('permission_sections')->where('name', $sectionName)->first();
            if (!$section) {
                $sectionId = DB::table('permission_sections')->insertGetId([
                    'name' => $sectionName,
                    'label' => $sectionLabel,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $sectionId = $section->id;
            }

            // Insert permissions for each action
            foreach ($actions as $action => $label) {
                $exists = DB::table('permissions')->where([
                    'section_id' => $sectionId,
                    'action' => $action,
                ])->exists();
                if (!$exists) {
                    $permissions[] = [
                        'section_id' => $sectionId,
                        'action' => $action,
                        'label' => $label . ' ' . $sectionLabel,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        if (!empty($permissions)) {
            DB::table('permissions')->insert($permissions);
        }
    }
}
