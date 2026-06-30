<?php

return [
    'backup' => [
        'path' => env('SECURITY_BACKUP_PATH', 'backups/database'),
        'retention_days' => (int) env('SECURITY_BACKUP_RETENTION_DAYS', 14),
        'schedule_time' => env('SECURITY_BACKUP_SCHEDULE_TIME', '02:30'),
        'compress' => (bool) env('SECURITY_BACKUP_COMPRESS', true),
        'timeout_seconds' => (int) env('SECURITY_BACKUP_TIMEOUT_SECONDS', 300),

        // Set absolute binaries if they are not available in PATH.
        'mysql_dump_binary' => env('MYSQL_DUMP_BINARY', 'mysqldump'),
        'pg_dump_binary' => env('PG_DUMP_BINARY', 'pg_dump'),
    ],

    'audit' => [
        'retention_days' => (int) env('SECURITY_AUDIT_RETENTION_DAYS', 365),
    ],
];
