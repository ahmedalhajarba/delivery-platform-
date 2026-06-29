<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait Auditable
{
    public static function bootAuditable()
    {
        static::created(function (Model $model) {
            self::audit('audit:created', $model, [
                'attributes' => $model->getAttributes(),
            ]);
        });

        static::updated(function (Model $model) {
            self::audit('audit:updated', $model, [
                'changes' => $model->getChanges(),
                'original' => $model->getOriginal(),
                'attributes' => $model->getAttributes(),
            ]);
        });

        static::deleted(function (Model $model) {
            self::audit('audit:deleted', $model, [
                'attributes' => $model->getAttributes(),
                'original' => $model->getOriginal(),
            ]);
        });

        static::restored(function (Model $model) {
            self::audit('audit:restored', $model, [
                'attributes' => $model->getAttributes(),
            ]);
        });
    }

    protected static function audit(string $description, Model $model, array $payload = []): void
    {
        $request = app()->runningInConsole() ? null : request();

        $data = [
            'event_uuid'   => (string) Str::uuid(),
            'action'       => $description,
            'description'  => $description,
            'subject_id'   => $model->id ?? null,
            'subject_type' => get_class($model),
            'user_id'      => auth()->id() ?? null,
            'properties'   => [
                'event' => $description,
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
    }
}
