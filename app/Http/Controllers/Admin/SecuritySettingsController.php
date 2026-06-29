<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SecuritySetting;
use Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class SecuritySettingsController extends Controller
{
    public function edit()
    {
        abort_if(Gate::denies('site_setting_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $securitySetting = SecuritySetting::query()->first();

        if (!$securitySetting) {
            $securitySetting = SecuritySetting::query()->create([
                'backup_path' => config('security.backup.path', 'backups/database'),
                'backup_retention_days' => (int) config('security.backup.retention_days', 14),
                'backup_schedule_time' => (string) config('security.backup.schedule_time', '02:30'),
                'backup_compress' => (bool) config('security.backup.compress', true),
                'backup_timeout_seconds' => (int) config('security.backup.timeout_seconds', 300),
                'mysql_dump_binary' => config('security.backup.mysql_dump_binary'),
                'pg_dump_binary' => config('security.backup.pg_dump_binary'),
                'audit_retention_days' => (int) config('security.audit.retention_days', 365),
            ]);
        }

        $backupDir = storage_path('app/' . trim((string) $securitySetting->backup_path, '/'));
        $backupFiles = [];

        if (File::exists($backupDir)) {
            $backupFiles = collect(File::files($backupDir))
                ->filter(function ($file) {
                    $ext = strtolower($file->getExtension());
                    return in_array($ext, ['sql', 'gz'], true);
                })
                ->sortByDesc(function ($file) {
                    return $file->getMTime();
                })
                ->take(20)
                ->map(function ($file) {
                    return [
                        'name' => $file->getFilename(),
                        'path' => $file->getPathname(),
                        'size_kb' => round($file->getSize() / 1024, 2),
                        'updated_at' => date('Y-m-d H:i:s', $file->getMTime()),
                    ];
                })
                ->values()
                ->all();
        }

        $backupAuditLogs = AuditLog::query()
            ->whereIn('action', ['security:backup-db', 'security:backup-db-failed'])
            ->latest('id')
            ->limit(20)
            ->get();

        return view('admin.securitySettings.edit', compact('securitySetting', 'backupFiles', 'backupAuditLogs', 'backupDir'));
    }

    public function update(Request $request)
    {
        abort_if(Gate::denies('site_setting_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $validated = $request->validate([
            'backup_path' => ['required', 'string', 'max:255'],
            'backup_retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'backup_schedule_time' => ['required', 'date_format:H:i'],
            'backup_compress' => ['nullable', 'boolean'],
            'backup_timeout_seconds' => ['required', 'integer', 'min:30', 'max:7200'],
            'mysql_dump_binary' => ['nullable', 'string', 'max:255'],
            'pg_dump_binary' => ['nullable', 'string', 'max:255'],
            'audit_retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);

        $validated['backup_compress'] = $request->boolean('backup_compress');

        SecuritySetting::query()->updateOrCreate(['id' => 1], $validated);

        return redirect()->route('admin.security-settings.edit')
            ->with('message', 'تم تحديث إعدادات الأمان بنجاح.');
    }

    public function runBackup()
    {
        abort_if(Gate::denies('site_setting_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $exitCode = Artisan::call('security:backup-db');
        $output = trim((string) Artisan::output());

        if ($exitCode !== 0) {
            return redirect()->route('admin.security-settings.edit')
                ->with('error', $output !== '' ? $output : 'فشل تنفيذ النسخة الاحتياطية.');
        }

        return redirect()->route('admin.security-settings.edit')
            ->with('message', $output !== '' ? $output : 'تم تنفيذ النسخة الاحتياطية بنجاح.');
    }
}
