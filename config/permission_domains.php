<?php

return [
    // Domain labels for grouping permissions in the UI
    'labels' => [
        // الأقسام الأساسية
        'dashboard' => 'لوحة التحكم',
        'user' => 'المستخدمين',
        'role' => 'الأدوار',
        'permission' => 'الصلاحيات',
        'notification' => 'الإشعارات',
        'audit_log' => 'سجلات التدقيق',
        'security' => 'الأمان',
        'setting' => 'الإعدادات',

        // البيانات الجغرافية
        'country' => 'الدول',
        'city' => 'المدن',
        'governorate' => 'المحافظات',
        'geo' => 'البيانات الجغرافية',

        // إدارة الزبائن والطلبات
        'customer' => 'العملاء',
        'order' => 'الطلبات',
        'shipment_tracking' => 'تتبع الشحنات',
        'order_returns' => 'إرجاع الطلبات',
        'insurances' => 'بوالص التأمين',
        'addresses' => 'العناوين',

        // المبيعات والتسويق
        'sales_leads' => 'العملاء المحتملون',
        'marketing_campaigns' => 'الحملات التسويقية',
        'sales_operations' => 'مكتب المبيعات',
        'discount_codes' => 'الأكواد والخصومات',
        'referral_links' => 'روابط الإحالة',
        'incentives' => 'الحوافز والعمولات',
        'sales_kpi' => 'مؤشرات المبيعات',
        'followups' => 'متابعة العملاء',
        'sales_team_assignments' => 'ربط فريق المبيعات',
        'sales_settlements' => 'مقاصة العمولات',
        'quotations' => 'عروض الأسعار',
        'contracts' => 'العقود والاتفاقيات',
        'account_activations' => 'تفعيل الحسابات',

        // المناديب والأسطول
        'courier_management' => 'إدارة المناديب',
        'route_plans' => 'خطوط السير',
        'trips' => 'الرحلات',
        'courier_settlements' => 'محاسبة المناديب',
        'support_tickets' => 'تذاكر الدعم',
        'courier_bookings' => 'حجز المندوب',
        'operations_settings' => 'الإعدادات التشغيلية',
        'vehicles' => 'المركبات والأسطول',
        'carrier_companies' => 'شركات الشحن',

        // إعدادات الطلبات والشحن
        'extra_services' => 'الخدمات الإضافية',
        'delivery_speed' => 'سرعة التوصيل',
        'shipping_prices' => 'أسعار الشحن',
        'intl_shipping' => 'الشحن الدولي',
        'order_settings' => 'إعدادات الطلبات',
        'waybill_settings' => 'إعدادات البوليصة',
        'cod_settlements' => 'مقاصة COD',

        // الاشتراكات
        'subscriptions' => 'نظام الاشتراكات',
        'subscription' => 'الاشتراكات',

        // المحتوى والصفحات
        'content' => 'محتوى الموقع',
        'terms_conditions' => 'الشروط والأحكام',

        // الفروع
        'branch' => 'الفروع',
        'branches' => 'الفروع',
        'branch_types' => 'أنواع الفروع',
        'branch_categories' => 'تصنيفات الفروع',

        // HR والموارد البشرية
        'hr' => 'الموارد البشرية',
        'employee' => 'الموظفين',

        // المالية
        'finance' => 'المالية',
        'invoice' => 'الفواتير',
        'receipts' => 'سندات القبض',
        'payroll' => 'الرواتب',
        'wallet' => 'المحافظ',
        'transaction' => 'المعاملات',
        'service_purchase' => 'شراء الخدمات',

        // الأقسام الأخرى
        'company' => 'الشركات',
        'categories' => 'التصنيفات',
        'features' => 'الخصائص',
        'client' => 'بوابة العميل',
        'other' => 'أقسام أخرى',
    ],

    // Map section to domain key
    'section_domain_map' => [
        'dashboard' => 'dashboard',
        'user' => 'user',
        'users' => 'user',
        'role' => 'role',
        'roles' => 'role',
        'permission' => 'permission',
        'permissions' => 'permission',
        'notification' => 'notification',
        'notifications' => 'notification',
        'audit_log' => 'audit_log',
        'audit_logs' => 'audit_log',
        'security' => 'security',
        'setting' => 'setting',
        'settings' => 'setting',

        'country' => 'country',
        'countries' => 'country',
        'city' => 'city',
        'cities' => 'city',
        'governorate' => 'governorate',
        'governorates' => 'governorate',
        'geo_locations' => 'geo',

        'company' => 'company',
        'companies' => 'company',
        'company_type' => 'company_type',
        'company_types' => 'company_type',
        'financial_officer' => 'financial_officer',
        'financial_officers' => 'financial_officer',

        'customer' => 'customer',
        'customers' => 'customer',
        'order' => 'order',
        'orders' => 'order',
        'subscription' => 'subscription',
        'subscriptions' => 'subscription',
        'support_ticket' => 'support',
        'support_tickets' => 'support',

        'categories' => 'categories',
        'features' => 'features',
        'finance' => 'finance',
        'hr' => 'hr',
        'branch' => 'branch',
        'branches' => 'branch',
        'employee' => 'employee',
        'employees' => 'employee',
        'sales' => 'sales',
        'service_purchase' => 'service_purchase',
        'invoice' => 'invoice',
        'wallet' => 'wallet',
        'transaction' => 'transaction',
        'client_orders' => 'client',
        'client_support_tickets' => 'client',
        'client_subscriptions' => 'client',
    ],

    // Map section prefix to domain key
    'prefix_domain_map' => [
        'dashboard_' => 'dashboard',
        'user_' => 'user',
        'users_' => 'user',
        'role_' => 'role',
        'roles_' => 'role',
        'permission_' => 'permission',
        'permissions_' => 'permission',
        'notification_' => 'notification',
        'notifications_' => 'notification',
        'audit_log_' => 'audit_log',
        'audit_logs_' => 'audit_log',
        'security_' => 'security',
        'setting_' => 'setting',
        'settings_' => 'setting',

        'country_' => 'country',
        'countries_' => 'country',
        'city_' => 'city',
        'cities_' => 'city',
        'governorate_' => 'governorate',
        'governorates_' => 'governorate',
        'geo_locations_' => 'geo',

        'company_' => 'company',
        'companies_' => 'company',
        'company_type_' => 'company_type',
        'company_types_' => 'company_type',
        'financial_officer_' => 'financial_officer',
        'financial_officers_' => 'financial_officer',

        'customer_' => 'customer',
        'customers_' => 'customer',
        'order_' => 'order',
        'orders_' => 'order',
        'subscription_' => 'subscription',
        'subscriptions_' => 'subscription',
        'support_ticket_' => 'support',
        'support_tickets_' => 'support',

        'categories_' => 'categories',
        'features_' => 'features',
        'finance_' => 'finance',
        'hr_' => 'hr',
        'branch_' => 'branch',
        'branches_' => 'branch',
        'employee_' => 'employee',
        'employees_' => 'employee',
        'sales_' => 'sales',
        'service_purchase_' => 'service_purchase',
        'invoice_' => 'invoice',
        'wallet_' => 'wallet',
        'transaction_' => 'transaction',
        'client_' => 'client',
    ],

    // Optional visual metadata used by permission matrix selector
    'meta' => [
        'dashboard' => ['icon' => 'bi-speedometer2', 'bg' => '#e9f2ff', 'color' => '#0b4c9c'],
        'user' => ['icon' => 'bi-people', 'bg' => '#eefaf5', 'color' => '#0f7a4a'],
        'role' => ['icon' => 'bi-shield-lock', 'bg' => '#fdf4e8', 'color' => '#9c5d00'],
        'permission' => ['icon' => 'bi-key', 'bg' => '#fff4f4', 'color' => '#b42318'],
        'finance' => ['icon' => 'bi-cash-stack', 'bg' => '#eef8ff', 'color' => '#125f8f'],
        'hr' => ['icon' => 'bi-person-vcard', 'bg' => '#f7f0ff', 'color' => '#5d2ea8'],
        'sales' => ['icon' => 'bi-graph-up-arrow', 'bg' => '#effbf1', 'color' => '#1e7d36'],
        'client' => ['icon' => 'bi-person-circle', 'bg' => '#eef5ff', 'color' => '#3455a5'],
        'other' => ['icon' => 'bi-grid-3x3-gap', 'bg' => '#f4f6f8', 'color' => '#3b4552'],
    ],
];
