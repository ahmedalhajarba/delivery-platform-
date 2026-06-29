<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class RestoreDeletedRecordsCommand extends Command
{
    protected $signature = 'security:restore-deleted
                            {model? : Model class or short model name (ex: Order)}
                            {ids?* : Soft-deleted record IDs to restore}
                            {--audit-log-id= : Restore using deletion audit log id}';

    protected $description = 'Restore soft-deleted records by model/id or by deletion audit log id.';

    public function handle(): int
    {
        $auditLogId = $this->option('audit-log-id');

        if ($auditLogId) {
            return $this->restoreFromAuditLog((int) $auditLogId);
        }

        $modelClass = $this->resolveModelClass((string) $this->argument('model'));
        if (!$modelClass) {
            $this->error('Model is required. Example: php artisan security:restore-deleted Order 125');
            return self::FAILURE;
        }

        if (!class_exists($modelClass)) {
            $this->error('Model class not found: ' . $modelClass);
            return self::FAILURE;
        }

        if (!in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $this->error('Model does not use SoftDeletes and cannot be restored safely: ' . $modelClass);
            return self::FAILURE;
        }

        $ids = array_filter((array) $this->argument('ids'), static function ($id) {
            return is_numeric($id);
        });

        if (empty($ids)) {
            $this->error('At least one numeric ID is required.');
            return self::FAILURE;
        }

        $restored = 0;
        $skipped = 0;

        foreach ($ids as $id) {
            $record = $modelClass::withTrashed()->find($id);

            if (!$record) {
                $this->warn("Record {$id} not found for {$modelClass}.");
                $skipped++;
                continue;
            }

            if (!method_exists($record, 'trashed') || !$record->trashed()) {
                $this->line("Record {$id} is already active.");
                $skipped++;
                continue;
            }

            $record->restore();
            $restored++;

            $this->recordAudit('security:restore-deleted', [
                'model' => $modelClass,
                'id' => (int) $id,
                'source' => 'direct',
            ], $record);
        }

        $this->info("Restored: {$restored}, Skipped: {$skipped}");

        return $restored > 0 ? self::SUCCESS : self::FAILURE;
    }

    private function restoreFromAuditLog(int $auditLogId): int
    {
        /** @var AuditLog|null $audit */
        $audit = AuditLog::query()->find($auditLogId);

        if (!$audit) {
            $this->error('Audit log not found.');
            return self::FAILURE;
        }

        if (!in_array((string) $audit->action, ['audit:deleted', 'security:hard-delete'], true)) {
            $this->error('Provided audit log is not a deletion event.');
            return self::FAILURE;
        }

        $modelClass = $this->resolveModelClassFromAudit($audit);
        if (!$modelClass || !class_exists($modelClass)) {
            $this->error('Unable to resolve model class from audit log.');
            return self::FAILURE;
        }

        if (!in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $this->error('Resolved model does not support SoftDeletes: ' . $modelClass);
            return self::FAILURE;
        }

        $recordId = (int) $audit->subject_id;
        if ($recordId <= 0) {
            $recordId = (int) Arr::get((array) $audit->properties, 'payload.attributes.id');
        }

        if ($recordId <= 0) {
            $this->error('Unable to resolve record id from audit log.');
            return self::FAILURE;
        }

        $record = $modelClass::withTrashed()->find($recordId);

        if (!$record) {
            $this->error('Record no longer exists in database. Audit-only recovery needs a dedicated re-create workflow.');
            return self::FAILURE;
        }

        if (!$record->trashed()) {
            $this->line('Record is already active.');
            return self::SUCCESS;
        }

        $record->restore();

        $this->recordAudit('security:restore-deleted', [
            'model' => $modelClass,
            'id' => $recordId,
            'source' => 'audit_log',
            'audit_log_id' => $auditLogId,
        ], $record);

        $this->info('Record restored successfully from audit event.');

        return self::SUCCESS;
    }

    private function resolveModelClass(?string $model): ?string
    {
        $model = trim((string) $model);
        if ($model === '') {
            return null;
        }

        if (class_exists($model)) {
            return $model;
        }

        $candidate = 'App\\Models\\' . Str::studly($model);

        return class_exists($candidate) ? $candidate : null;
    }

    private function resolveModelClassFromAudit(AuditLog $audit): ?string
    {
        $subjectType = (string) $audit->subject_type;
        if ($subjectType === '') {
            return null;
        }

        if (Str::contains($subjectType, '#')) {
            $subjectType = (string) Str::before($subjectType, '#');
        }

        return $this->resolveModelClass($subjectType) ?: $subjectType;
    }

    private function recordAudit(string $description, array $properties, Model $record): void
    {
        $data = [
            'event_uuid' => (string) Str::uuid(),
            'action' => $description,
            'description' => $description,
            'subject_id' => $record->getKey(),
            'subject_type' => get_class($record),
            'user_id' => auth()->id() ?? null,
            'properties' => [
                'event' => $description,
                'payload' => $properties,
                'console' => true,
            ],
            'request_method' => 'CLI',
            'request_url' => 'artisan security:restore-deleted',
        ];

        if (!AuditLog::hasColumn('event_uuid')) {
            unset($data['event_uuid'], $data['action'], $data['request_method'], $data['request_url'], $data['user_agent']);
        }

        AuditLog::createSafely($data);
    }
}
