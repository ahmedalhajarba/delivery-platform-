<?php

return [
    'default_actions' => [
        'access',
        'show',
        'create',
        'edit',
        'delete',
        'print',
    ],

    'action_columns' => [
        'access' => 'الدخول',
        'show' => 'العرض',
        'create' => 'الإضافة',
        'edit' => 'التعديل',
        'delete' => 'الحذف',
        'print' => 'الطباعة',
        'approve' => 'الاعتماد',
        'export' => 'التصدير',
        'import' => 'الاستيراد',
        'manage' => 'الإدارة',
    ],

    'arabic_sections' => config('permission_domains.labels', []),
];
