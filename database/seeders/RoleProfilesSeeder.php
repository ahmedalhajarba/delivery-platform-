<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class RoleProfilesSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = Permission::with('section')->get();
        if ($permissions->isEmpty()) {
            if ($this->command) {
                $this->command->warn('RoleProfilesSeeder: لا توجد صلاحيات في النظام، تم تخطي إنشاء البروفايلات.');
            }
            return;
        }

        $profiles = [
            [
                'title' => 'System Admin',
                'slug' => 'system_admin',
                'description' => 'صلاحية كاملة على المنصة',
                'is_default' => false,
                'is_system' => true,
                'resolver' => function (Collection $all) {
                    return $all;
                },
            ],
            [
                'title' => 'Operations Manager',
                'slug' => 'operations_manager',
                'description' => 'إدارة العمليات التشغيلية اليومية',
                'resolver' => function (Collection $all) {
                    return $this->matchByRules($all, [
                        'include_domains' => ['order', 'customer', 'branch', 'geo', 'notification', 'support', 'service_purchase'],
                        'include_actions' => ['access', 'show', 'create', 'edit', 'delete', 'print', 'approve'],
                    ]);
                },
            ],
            [
                'title' => 'Finance Manager',
                'slug' => 'finance_manager',
                'description' => 'إدارة الفواتير والمعاملات المالية',
                'resolver' => function (Collection $all) {
                    return $this->matchByRules($all, [
                        'include_domains' => ['finance', 'invoice', 'wallet', 'transaction'],
                        'include_actions' => ['access', 'show', 'create', 'edit', 'delete', 'print', 'export', 'import', 'approve'],
                    ]);
                },
            ],
            [
                'title' => 'HR Manager',
                'slug' => 'hr_manager',
                'description' => 'إدارة ملفات الموارد البشرية والموظفين',
                'resolver' => function (Collection $all) {
                    return $this->matchByRules($all, [
                        'include_domains' => ['hr', 'employee', 'user'],
                        'include_actions' => ['access', 'show', 'create', 'edit', 'approve'],
                        'exclude_actions' => ['delete'],
                    ]);
                },
            ],
            [
                'title' => 'Sales Agent',
                'slug' => 'sales_agent',
                'description' => 'متابعة العملاء والمبيعات والعروض',
                'resolver' => function (Collection $all) {
                    return $this->matchByRules($all, [
                        'include_domains' => ['sales', 'customer', 'order', 'invoice', 'service_purchase'],
                        'include_actions' => ['access', 'show', 'create', 'edit', 'print'],
                    ]);
                },
            ],
            [
                'title' => 'Support Agent',
                'slug' => 'support_agent',
                'description' => 'إدارة تذاكر الدعم والمتابعة مع العملاء',
                'resolver' => function (Collection $all) {
                    return $this->matchByRules($all, [
                        'include_domains' => ['support', 'customer', 'notification', 'client'],
                        'include_actions' => ['access', 'show', 'create', 'edit'],
                    ]);
                },
            ],
            [
                'title' => 'Read Only Auditor',
                'slug' => 'read_only_auditor',
                'description' => 'عرض البيانات والتقارير بدون تعديل',
                'resolver' => function (Collection $all) {
                    return $this->matchByRules($all, [
                        'include_actions' => ['access', 'show', 'print', 'export'],
                    ]);
                },
            ],
        ];

        foreach ($profiles as $profile) {
            $role = Role::query()->firstOrCreate(
                ['slug' => $profile['slug']],
                [
                    'title' => $profile['title'],
                    'name' => $profile['title'],
                    'label' => $profile['description'],
                    'is_default' => $profile['is_default'] ?? false,
                    'is_system' => $profile['is_system'] ?? false,
                ]
            );

            if (blank($role->title)) {
                $role->title = $profile['title'];
            }
            if (blank($role->name)) {
                $role->name = $profile['title'];
            }
            if (blank($role->label)) {
                $role->label = $profile['description'];
            }
            $role->save();

            $permissionSet = ($profile['resolver'])($permissions)->pluck('id')->unique()->values()->all();
            $role->permissions()->sync($permissionSet);
        }

        if ($this->command) {
            $this->command->info('RoleProfilesSeeder: تم إنشاء/تحديث بروفايلات الأدوار وربط الصلاحيات بنجاح.');
        }
    }

    private function matchByRules(Collection $permissions, array $rules): Collection
    {
        $includeDomains = collect($rules['include_domains'] ?? [])->map(fn ($v) => strtolower((string) $v))->all();
        $includeActions = collect($rules['include_actions'] ?? [])->map(fn ($v) => strtolower((string) $v))->all();
        $excludeActions = collect($rules['exclude_actions'] ?? [])->map(fn ($v) => strtolower((string) $v))->all();

        return $permissions->filter(function (Permission $permission) use ($includeDomains, $includeActions, $excludeActions) {
            [$section, $action] = $this->permissionSectionAndAction($permission);
            $domain = Permission::resolveDomainKeyForSection($section);

            if (!empty($includeDomains) && !in_array($domain, $includeDomains, true)) {
                return false;
            }

            if (!empty($includeActions) && !in_array($action, $includeActions, true)) {
                return false;
            }

            if (!empty($excludeActions) && in_array($action, $excludeActions, true)) {
                return false;
            }

            return true;
        })->values();
    }

    private function permissionSectionAndAction(Permission $permission): array
    {
        $section = (string) ($permission->section->name ?? $permission->section ?? '');
        $action = (string) ($permission->action_key ?? $permission->action ?? '');

        if ($section === '' || $action === '') {
            [$parsedSection, $parsedAction] = Permission::parseTitle((string) ($permission->name ?? $permission->title ?? ''));
            $section = $section !== '' ? $section : $parsedSection;
            $action = $action !== '' ? $action : (string) $parsedAction;
        }

        return [
            strtolower(trim($section)) ?: 'other',
            strtolower(trim($action)) ?: 'access',
        ];
    }
}
