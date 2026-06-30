<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Permission;
use App\Models\PermissionSection;

class ComprehensivePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // إيقاف تحديثات الجداول الخارجية مؤقتاً
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // مسح الصلاحيات والأقسام القديمة
        DB::table('permission_role')->truncate();
        DB::table('permissions')->truncate();
        DB::table('permission_sections')->truncate();
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $sections = $this->getPermissionSections();
        $permissions = $this->getPermissions();

        // إدراج أقسام الصلاحيات
        foreach ($sections as $section) {
            PermissionSection::create($section);
        }

        // إدراج الصلاحيات
        foreach ($permissions as $permission) {
            // تنظيف البيانات - إزالة الحقول غير الموجودة
            $permData = [
                'name' => $permission['name'],
                'label' => $permission['label'],
                'action' => $permission['action'],
                'section_id' => $permission['section_id'],
                'action_key' => $permission['action'],
            ];
            Permission::create($permData);
        }

        $this->command->info('✓ تم إنشاء نظام الصلاحيات الشامل بنجاح');
        $this->command->info('تم إنشاء ' . count($sections) . ' قسم صلاحيات و ' . count($permissions) . ' صلاحية');
    }

    /**
     * الحصول على قائمة أقسام الصلاحيات
     */
    private function getPermissionSections()
    {
        return [
            // 1. قسم المستخدمون
            ['name' => 'users', 'label' => 'إدارة المستخدمين'],
            ['name' => 'roles', 'label' => 'إدارة الأدوار والصلاحيات'],
            ['name' => 'permissions', 'label' => 'الصلاحيات'],
            ['name' => 'geo_locations', 'label' => 'المواقع الجغرافية'],
            
            // 2. إدارة الزبائن
            ['name' => 'customers', 'label' => 'إدارة العملاء'],
            
            // 3. المبيعات والتسويق
            ['name' => 'sales_leads', 'label' => 'العملاء المحتملون'],
            ['name' => 'marketing_campaigns', 'label' => 'الحملات التسويقية'],
            ['name' => 'sales_operations', 'label' => 'مكتب المبيعات الموحد'],
            ['name' => 'discount_codes', 'label' => 'الأكواد والخصومات'],
            ['name' => 'referral_links', 'label' => 'روابط إحالة المبيعات'],
            ['name' => 'incentives', 'label' => 'الحوافز والعمولات'],
            ['name' => 'sales_kpi', 'label' => 'مؤشرات أداء المبيعات'],
            ['name' => 'followups', 'label' => 'متابعة العملاء'],
            ['name' => 'sales_team_assignments', 'label' => 'ربط فريق المبيعات بالمناطق'],
            ['name' => 'sales_settlements', 'label' => 'مقاصة العمولات والتارقت'],
            ['name' => 'quotations', 'label' => 'عروض الأسعار'],
            ['name' => 'contracts', 'label' => 'العقود والاتفاقيات'],
            ['name' => 'account_activations', 'label' => 'تفعيل الحسابات'],
            
            // 4. الطلبات
            ['name' => 'orders', 'label' => 'إدارة الطلبات'],
            ['name' => 'shipment_tracking', 'label' => 'تتبع الشحنات'],
            ['name' => 'order_returns', 'label' => 'طلبات الإرجاع'],
            ['name' => 'insurances', 'label' => 'إدارة بوالص التأمين'],
            ['name' => 'cod_settlements', 'label' => 'مقاصة COD'],
            ['name' => 'extra_services', 'label' => 'إعدادات الخدمات الإضافية'],
            ['name' => 'delivery_speed', 'label' => 'إعدادات سرعة التوصيل'],
            ['name' => 'shipping_prices', 'label' => 'أسعار الشحن بالمناطق'],
            ['name' => 'intl_shipping', 'label' => 'أسعار الشحن الدولي'],
            
            // 5. المناديب والأسطول
            ['name' => 'courier_management', 'label' => 'إدارة المناديب'],
            ['name' => 'route_plans', 'label' => 'خطوط السير'],
            ['name' => 'trips', 'label' => 'الرحلات وتجميع البوالص'],
            ['name' => 'courier_settlements', 'label' => 'محاسبة السائقين والمناديب'],
            ['name' => 'support_tickets', 'label' => 'الدعم الفني والتذاكر'],
            ['name' => 'courier_bookings', 'label' => 'طلبات حجز المندوب'],
            ['name' => 'operations_settings', 'label' => 'الإعدادات التشغيلية'],
            ['name' => 'vehicles', 'label' => 'الأسطول والمركبات'],
            ['name' => 'carrier_companies', 'label' => 'شركات الشحن الناقلة'],
            ['name' => 'order_settings', 'label' => 'إعدادات الطلبات'],
            ['name' => 'waybill_settings', 'label' => 'إعدادات البوليصة'],
            ['name' => 'addresses', 'label' => 'إدارة العناوين'],
            
            // 6. نظام الاشتراكات
            ['name' => 'subscriptions_dashboard', 'label' => 'لوحة الاشتراكات'],
            ['name' => 'subscriptions_plans', 'label' => 'خطط الاشتراك'],
            ['name' => 'user_subscriptions', 'label' => 'اشتراكات المستخدمين'],
            ['name' => 'subscriptions_settings', 'label' => 'إعدادات الاشتراكات'],
            ['name' => 'manual_subscriptions', 'label' => 'إضافة اشتراك يدوي'],
            ['name' => 'extra_charges', 'label' => 'الرسوم الإضافية'],
            ['name' => 'carrier_quotas', 'label' => 'حدود البوالص الشهرية'],
            
            // 7. صفحات المحتوى
            ['name' => 'terms_conditions', 'label' => 'الشروط والأحكام'],
            ['name' => 'legal_pages', 'label' => 'سياسة الخصوصية'],
            ['name' => 'issues_management', 'label' => 'إدارة القضايا والمحامين'],
            
            // 8. الفروع
            ['name' => 'branches', 'label' => 'إدارة الفروع'],
            ['name' => 'branch_capacities', 'label' => 'السعة التشغيلية للفروع'],
            ['name' => 'branch_kpis', 'label' => 'مؤشرات أداء الفروع'],
            ['name' => 'branch_service_areas', 'label' => 'توزيع المناطق على الفروع'],
            ['name' => 'branch_types', 'label' => 'أنواع الفروع'],
            ['name' => 'branch_categories', 'label' => 'تصنيفات الفروع'],
            ['name' => 'branch_sections', 'label' => 'أقسام الفروع'],
            ['name' => 'branch_employees', 'label' => 'موظفو الفروع'],
            
            // 9. الإعدادات
            ['name' => 'site_settings', 'label' => 'إعدادات الموقع'],
            ['name' => 'security_settings', 'label' => 'إعدادات الأمان'],
            ['name' => 'tax_settings', 'label' => 'إعدادات الضرائب'],
            ['name' => 'currencies', 'label' => 'إدارة العملات'],
            
            // 10. HR والحوكمة
            ['name' => 'hr_tasks', 'label' => 'مهام الموارد البشرية'],
            ['name' => 'employee_management', 'label' => 'إدارة الموظفين و KPI'],
            ['name' => 'hr_approvals', 'label' => 'مسارات الموافقات'],
            ['name' => 'approval_requests', 'label' => 'طلبات الموافقات'],
            
            // 11. المالية
            ['name' => 'finance_dashboard', 'label' => 'لوحة المالية'],
            ['name' => 'receipts', 'label' => 'سندات القبض'],
            ['name' => 'invoices', 'label' => 'الفواتير'],
            ['name' => 'finance_documents', 'label' => 'المصاريف والمشتريات وسندات الصرف'],
            ['name' => 'payroll', 'label' => 'إدارة الرواتب'],
            ['name' => 'employee_deductions', 'label' => 'خصومات الموظفين'],
            ['name' => 'account_statements', 'label' => 'كشوف الحساب'],
            ['name' => 'service_purchases', 'label' => 'مشتريات خدمات المنصة'],
            ['name' => 'technical_integrations', 'label' => 'الربط التقني للعملاء'],
            ['name' => 'billing_settings', 'label' => 'إعدادات الفوترة البنكية'],
            ['name' => 'cashboxes', 'label' => 'الصناديق المالية'],
            ['name' => 'deferred_accounts', 'label' => 'حسابات الآجل'],
            
            // 12. النظام
            ['name' => 'audit_logs', 'label' => 'سجل التدقيق'],
        ];
    }

    /**
     * الحصول على قائمة الصلاحيات
     */
    private function getPermissions()
    {
        $actions = ['access', 'create', 'read', 'update', 'delete', 'print', 'export'];
        $permissions = [];

        $sectionNames = [
            'users', 'roles', 'permissions', 'geo_locations',
            'customers',
            'sales_leads', 'marketing_campaigns', 'sales_operations', 'discount_codes',
            'referral_links', 'incentives', 'sales_kpi', 'followups',
            'sales_team_assignments', 'sales_settlements', 'quotations', 'contracts',
            'account_activations',
            'orders', 'shipment_tracking', 'order_returns', 'insurances',
            'cod_settlements', 'extra_services', 'delivery_speed', 'shipping_prices',
            'intl_shipping',
            'courier_management', 'route_plans', 'trips', 'courier_settlements',
            'support_tickets', 'courier_bookings', 'operations_settings', 'vehicles',
            'carrier_companies', 'order_settings', 'waybill_settings', 'addresses',
            'subscriptions_dashboard', 'subscriptions_plans', 'user_subscriptions',
            'subscriptions_settings', 'manual_subscriptions', 'extra_charges',
            'carrier_quotas',
            'terms_conditions', 'legal_pages', 'issues_management',
            'branches', 'branch_capacities', 'branch_kpis', 'branch_service_areas',
            'branch_types', 'branch_categories', 'branch_sections', 'branch_employees',
            'site_settings', 'security_settings', 'tax_settings', 'currencies',
            'hr_tasks', 'employee_management', 'hr_approvals', 'approval_requests',
            'finance_dashboard', 'receipts', 'invoices', 'finance_documents',
            'payroll', 'employee_deductions', 'account_statements', 'service_purchases',
            'technical_integrations', 'billing_settings', 'cashboxes', 'deferred_accounts',
            'audit_logs'
        ];

        $actionLabels = [
            'access' => 'الوصول',
            'create' => 'الإضافة',
            'read' => 'العرض',
            'update' => 'التعديل',
            'delete' => 'الحذف',
            'print' => 'الطباعة',
            'export' => 'التصدير',
        ];

        $sectionMap = [
            'users' => 1,
            'roles' => 2,
            'permissions' => 3,
            'geo_locations' => 4,
            'customers' => 5,
            'sales_leads' => 6,
            'marketing_campaigns' => 7,
            'sales_operations' => 8,
            'discount_codes' => 9,
            'referral_links' => 10,
            'incentives' => 11,
            'sales_kpi' => 12,
            'followups' => 13,
            'sales_team_assignments' => 14,
            'sales_settlements' => 15,
            'quotations' => 16,
            'contracts' => 17,
            'account_activations' => 18,
            'orders' => 19,
            'shipment_tracking' => 20,
            'order_returns' => 21,
            'insurances' => 22,
            'cod_settlements' => 23,
            'extra_services' => 24,
            'delivery_speed' => 25,
            'shipping_prices' => 26,
            'intl_shipping' => 27,
            'courier_management' => 28,
            'route_plans' => 29,
            'trips' => 30,
            'courier_settlements' => 31,
            'support_tickets' => 32,
            'courier_bookings' => 33,
            'operations_settings' => 34,
            'vehicles' => 35,
            'carrier_companies' => 36,
            'order_settings' => 37,
            'waybill_settings' => 38,
            'addresses' => 39,
            'subscriptions_dashboard' => 40,
            'subscriptions_plans' => 41,
            'user_subscriptions' => 42,
            'subscriptions_settings' => 43,
            'manual_subscriptions' => 44,
            'extra_charges' => 45,
            'carrier_quotas' => 46,
            'terms_conditions' => 47,
            'legal_pages' => 48,
            'issues_management' => 49,
            'branches' => 50,
            'branch_capacities' => 51,
            'branch_kpis' => 52,
            'branch_service_areas' => 53,
            'branch_types' => 54,
            'branch_categories' => 55,
            'branch_sections' => 56,
            'branch_employees' => 57,
            'site_settings' => 58,
            'security_settings' => 59,
            'tax_settings' => 60,
            'currencies' => 61,
            'hr_tasks' => 62,
            'employee_management' => 63,
            'hr_approvals' => 64,
            'approval_requests' => 65,
            'finance_dashboard' => 66,
            'receipts' => 67,
            'invoices' => 68,
            'finance_documents' => 69,
            'payroll' => 70,
            'employee_deductions' => 71,
            'account_statements' => 72,
            'service_purchases' => 73,
            'technical_integrations' => 74,
            'billing_settings' => 75,
            'cashboxes' => 76,
            'deferred_accounts' => 77,
            'audit_logs' => 78,
        ];

        foreach ($sectionNames as $section) {
            // إضافة صلاحية "الوصول" للقسم
            $permissions[] = [
                'name' => $section . '_access',
                'label' => 'الوصول إلى ' . $section,
                'action' => 'access',
                'section_id' => $sectionMap[$section],
            ];

            // إضافة صلاحيات أخرى حسب نوع القسم
            $applicableActions = $this->getApplicableActions($section);
            foreach ($applicableActions as $action) {
                if ($action !== 'access') {
                    $permissions[] = [
                        'name' => $section . '_' . $action,
                        'label' => $actionLabels[$action] . ' - ' . $section,
                        'action' => $action,
                        'section_id' => $sectionMap[$section],
                    ];
                }
            }
        }

        return $permissions;
    }

    /**
     * الحصول على الصلاحيات المطبقة حسب نوع القسم
     */
    private function getApplicableActions($section)
    {
        // الأقسام التي لا تحتاج صلاحيات حذف
        $noDeleteSections = [
            'audit_logs', 'account_statements', 'receipts', 'invoices',
            'finance_documents', 'geo_locations', 'currencies'
        ];

        // الأقسام التي تحتاج صلاحيات طباعة/تصدير
        $printExportSections = [
            'orders', 'invoices', 'receipts', 'finance_documents',
            'payroll', 'quotations', 'contracts', 'account_statements'
        ];

        $actions = ['access'];

        // أقسام الإعدادات - بدون إضافة/حذف
        $settingsSections = [
            'site_settings', 'security_settings', 'tax_settings', 'operations_settings',
            'extra_services', 'delivery_speed', 'subscriptions_settings',
            'billing_settings'
        ];

        if (in_array($section, $settingsSections)) {
            return array_merge($actions, ['read', 'update']);
        }

        // أقسام السجلات - بدون تعديل/حذف
        $logSections = ['audit_logs'];
        if (in_array($section, $logSections)) {
            return array_merge($actions, ['read', 'export']);
        }

        // الأقسام العادية
        $actions = array_merge($actions, ['create', 'read', 'update']);

        if (!in_array($section, $noDeleteSections)) {
            $actions[] = 'delete';
        }

        if (in_array($section, $printExportSections)) {
            $actions = array_merge($actions, ['print', 'export']);
        }

        return $actions;
    }
}
