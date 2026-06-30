<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // ===== 1. إنشاء الصلاحيات =====
        $permissions = [
            // Dashboard
            ['name' => 'view_dashboard', 'display_name' => 'عرض لوحة التحكم', 'module' => 'dashboard', 'action' => 'view'],
            
            // Users
            ['name' => 'view_users', 'display_name' => 'عرض المستخدمين', 'module' => 'users', 'action' => 'view'],
            ['name' => 'create_users', 'display_name' => 'إضافة مستخدمين', 'module' => 'users', 'action' => 'create'],
            ['name' => 'edit_users', 'display_name' => 'تعديل مستخدمين', 'module' => 'users', 'action' => 'edit'],
            ['name' => 'delete_users', 'display_name' => 'حذف مستخدمين', 'module' => 'users', 'action' => 'delete'],
            
            // Roles
            ['name' => 'view_roles', 'display_name' => 'عرض الأدوار', 'module' => 'roles', 'action' => 'view'],
            ['name' => 'create_roles', 'display_name' => 'إضافة أدوار', 'module' => 'roles', 'action' => 'create'],
            ['name' => 'edit_roles', 'display_name' => 'تعديل أدوار', 'module' => 'roles', 'action' => 'edit'],
            ['name' => 'delete_roles', 'display_name' => 'حذف أدوار', 'module' => 'roles', 'action' => 'delete'],
            ['name' => 'manage_roles', 'display_name' => 'إدارة الأدوار', 'module' => 'roles', 'action' => 'manage'],
            
            // Permissions
            ['name' => 'view_permissions', 'display_name' => 'عرض الصلاحيات', 'module' => 'permissions', 'action' => 'view'],
            ['name' => 'create_permissions', 'display_name' => 'إضافة صلاحيات', 'module' => 'permissions', 'action' => 'create'],
            ['name' => 'edit_permissions', 'display_name' => 'تعديل صلاحيات', 'module' => 'permissions', 'action' => 'edit'],
            ['name' => 'delete_permissions', 'display_name' => 'حذف صلاحيات', 'module' => 'permissions', 'action' => 'delete'],
            ['name' => 'manage_permissions', 'display_name' => 'إدارة الصلاحيات', 'module' => 'permissions', 'action' => 'manage'],
            
            // Orders
            ['name' => 'view_orders', 'display_name' => 'عرض الطلبات', 'module' => 'orders', 'action' => 'view'],
            ['name' => 'create_orders', 'display_name' => 'إنشاء طلبات', 'module' => 'orders', 'action' => 'create'],
            ['name' => 'edit_orders', 'display_name' => 'تعديل طلبات', 'module' => 'orders', 'action' => 'edit'],
            ['name' => 'delete_orders', 'display_name' => 'حذف طلبات', 'module' => 'orders', 'action' => 'delete'],
            ['name' => 'approve_orders', 'display_name' => 'الموافقة على الطلبات', 'module' => 'orders', 'action' => 'approve'],
            
            // Finance
            ['name' => 'view_finance', 'display_name' => 'عرض المالية', 'module' => 'finance', 'action' => 'view'],
            ['name' => 'manage_finance', 'display_name' => 'إدارة المالية', 'module' => 'finance', 'action' => 'manage'],
            
            // HR
            ['name' => 'view_hr', 'display_name' => 'عرض الموارد البشرية', 'module' => 'hr', 'action' => 'view'],
            ['name' => 'manage_hr', 'display_name' => 'إدارة الموارد البشرية', 'module' => 'hr', 'action' => 'manage'],
            
            // Customers
            ['name' => 'view_customers', 'display_name' => 'عرض العملاء', 'module' => 'customers', 'action' => 'view'],
            ['name' => 'create_customers', 'display_name' => 'إضافة عملاء', 'module' => 'customers', 'action' => 'create'],
            ['name' => 'edit_customers', 'display_name' => 'تعديل عملاء', 'module' => 'customers', 'action' => 'edit'],
            ['name' => 'delete_customers', 'display_name' => 'حذف عملاء', 'module' => 'customers', 'action' => 'delete'],
            
            // Couriers
            ['name' => 'view_couriers', 'display_name' => 'عرض المناديب', 'module' => 'couriers', 'action' => 'view'],
            ['name' => 'manage_couriers', 'display_name' => 'إدارة المناديب', 'module' => 'couriers', 'action' => 'manage'],
            
            // Branches
            ['name' => 'view_branches', 'display_name' => 'عرض الفروع', 'module' => 'branches', 'action' => 'view'],
            ['name' => 'manage_branches', 'display_name' => 'إدارة الفروع', 'module' => 'branches', 'action' => 'manage'],
            
            // Settings
            ['name' => 'manage_settings', 'display_name' => 'إدارة الإعدادات', 'module' => 'settings', 'action' => 'manage'],
            
            // Reports
            ['name' => 'view_reports', 'display_name' => 'عرض التقارير', 'module' => 'reports', 'action' => 'view'],
            ['name' => 'export_reports', 'display_name' => 'تصدير التقارير', 'module' => 'reports', 'action' => 'export'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm['name']], $perm);
        }

        // ===== 2. إنشاء الأدوار =====
        $roles = [
            'admin' => ['display_name' => 'مدير النظام', 'description' => 'صلاحية كاملة على النظام', 'is_default' => false, 'is_system' => true],
            'manager' => ['display_name' => 'مدير', 'description' => 'إدارة العمليات اليومية', 'is_default' => false, 'is_system' => false],
            'employee' => ['display_name' => 'موظف', 'description' => 'صلاحيات محدودة', 'is_default' => false, 'is_system' => false],
            'courier' => ['display_name' => 'مندوب توصيل', 'description' => 'إدارة الطلبات والتوصيل', 'is_default' => false, 'is_system' => false],
            'customer' => ['display_name' => 'عميل', 'description' => 'صلاحيات العميل', 'is_default' => true, 'is_system' => false],
        ];

        foreach ($roles as $name => $data) {
            Role::firstOrCreate(['name' => $name], $data);
        }

        // ===== 3. تعيين الصلاحيات لكل دور =====
        $rolePermissions = [
            'admin' => Permission::pluck('name')->toArray(),
            'manager' => [
                'view_dashboard', 'view_users', 'create_users', 'edit_users',
                'view_orders', 'create_orders', 'edit_orders', 'approve_orders',
                'view_finance', 'view_customers', 'create_customers', 'edit_customers',
                'view_reports', 'export_reports'
            ],
            'employee' => [
                'view_dashboard', 'view_orders', 'create_orders', 'edit_orders',
                'view_customers', 'view_couriers'
            ],
            'courier' => [
                'view_dashboard', 'view_orders', 'edit_orders',
            ],
            'customer' => [
                'view_orders', 'create_orders',
            ],
        ];

        foreach ($rolePermissions as $roleName => $perms) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->syncPermissions($perms);
            }
        }

        // ===== 4. تعيين دور Admin للمستخدم الأول =====
        $admin = User::where('email', 'admin@admin.com')->first();
        if ($admin) {
            $admin->assignRole('admin');
        }

        // ===== 5. تعيين دور Customer للمستخدمين العاديين =====
        $users = User::where('email', '!=', 'admin@admin.com')->get();
        foreach ($users as $user) {
            if (!$user->hasRole('admin')) {
                $user->assignRole('customer');
            }
        }

        $this->command->info('✅ Roles and Permissions seeded successfully!');
        $this->command->info('📧 Admin Email: admin@admin.com');
        $this->command->info('🔑 Password: 12345678');
    }
}