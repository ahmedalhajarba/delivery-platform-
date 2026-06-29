<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecuritySetting extends Model
{
    public $table = 'security_settings';

    protected $fillable = [
        'backup_path',
        'backup_retention_days',
        'backup_schedule_time',
        'backup_compress',
        'backup_timeout_seconds',
        'mysql_dump_binary',
        'pg_dump_binary',
        'audit_retention_days',
    ];

    protected $casts = [
        'backup_compress' => 'boolean',
        'backup_retention_days' => 'integer',
        'backup_timeout_seconds' => 'integer',
        'audit_retention_days' => 'integer',
    ];
}
