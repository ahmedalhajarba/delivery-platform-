<?php

namespace App\Listeners;

use App\Models\AuditLog;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class LogModelChanges
{
    /**
     * معالج أحداث الإنشاء
     * @param Model $model
     */
    public function onCreated($eventOrModel, ?array $payload = null): void
    {
        $model = $this->extractModel($eventOrModel, $payload);
        if (!$model) {
            return;
        }

        $this->logChange($model, 'audit:created', [
            'attributes' => $model->getAttributes(),
        ]);
    }

    /**
     * معالج أحداث التحديث
     * @param Model $model
     */
    public function onUpdated($eventOrModel, ?array $payload = null): void
    {
        $model = $this->extractModel($eventOrModel, $payload);
        if (!$model) {
            return;
        }

        $this->logChange($model, 'audit:updated', [
            'changes' => $model->getChanges(),
            'original' => $model->getOriginal(),
            'attributes' => $model->getAttributes(),
        ]);
    }

    /**
     * معالج أحداث الحذف
     * @param Model $model
     */
    public function onDeleted($eventOrModel, ?array $payload = null): void
    {
        $model = $this->extractModel($eventOrModel, $payload);
        if (!$model) {
            return;
        }

        $this->logChange($model, 'audit:deleted', [
            'attributes' => $model->getAttributes(),
            'original' => $model->getOriginal(),
        ]);
    }

    /**
     * معالج أحداث الاستعادة
     * @param Model $model
     */
    public function onRestored($eventOrModel, ?array $payload = null): void
    {
        $model = $this->extractModel($eventOrModel, $payload);
        if (!$model) {
            return;
        }

        $this->logChange($model, 'audit:restored', [
            'attributes' => $model->getAttributes(),
        ]);
    }

    /**
     * يدعم حالتين:
     * 1) الاستدعاء المباشر بـ Model.
     * 2) الاستماع إلى eloquent.* حيث تصل ($eventName, $payload).
     */
    private function extractModel($eventOrModel, ?array $payload = null): ?Model
    {
        if ($eventOrModel instanceof Model) {
            return $eventOrModel;
        }

        $candidate = is_array($payload) ? ($payload[0] ?? null) : null;
        return $candidate instanceof Model ? $candidate : null;
    }

    /**
     * تسجيل التغيير
     */
    protected function logChange(Model $model, string $action, array $payload = []): void
    {
        // تجاهل النماذج التي لديها Auditable trait (لتجنب الازدواج)
        if ($this->hasAuditableTrait($model)) {
            return;
        }

        // تجاهل بعض النماذج غير المهمة
        if ($this->shouldIgnoreModel($model)) {
            return;
        }

        try {
            $request = app()->runningInConsole() ? null : request();

            $data = [
                'event_uuid'   => (string) Str::uuid(),
                'action'       => $action,
                'description'  => $action,
                'subject_id'   => $model->id ?? null,
                'subject_type' => get_class($model),
                'user_id'      => auth()->id() ?? null,
                'properties'   => [
                    'event' => $action,
                    'payload' => $payload,
                    'request' => [
                        'method' => $request ? $request->method() : null,
                        'url' => $request ? $request->fullUrl() : null,
                        'route' => $request && $request->route() ? $request->route()->getName() : null,
                        'ip' => $request ? $request->ip() : null,
                        'user_agent' => $request ? $request->userAgent() : null,
                    ],
                ],
                'host'         => $request ? $request->ip() : null,
                'request_method' => $request ? $request->method() : null,
                'request_url' => $request ? $request->fullUrl() : null,
                'user_agent' => $request ? $request->userAgent() : null,
            ];

            if (!AuditLog::hasColumn('event_uuid')) {
                unset($data['event_uuid'], $data['action'], $data['request_method'], $data['request_url'], $data['user_agent']);
            }

            AuditLog::createSafely($data);
        } catch (\Exception $e) {
            // تجاهل الأخطاء لتجنب توقف التطبيق
            Log::warning('خطأ في تسجيل التدقيق: ' . $e->getMessage());
        }
    }

    /**
     * التحقق مما إذا كان النموذج يستخدم Auditable trait
     */
    protected function hasAuditableTrait(Model $model): bool
    {
        $traits = class_uses_recursive($model);
        return isset($traits[Auditable::class]);
    }

    /**
     * تحديد النماذج التي يجب تجاهلها
     */
    protected function shouldIgnoreModel(Model $model): bool
    {
        $ignoredModels = [
            'App\Models\AuditLog',
            'App\Models\ActivityLog',
        ];

        return in_array(get_class($model), $ignoredModels, true);
    }
}
