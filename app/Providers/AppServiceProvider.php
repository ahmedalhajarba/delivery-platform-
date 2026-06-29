<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\SecuritySetting;
use App\Models\SiteSetting;
use App\Models\SocialLink;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //

    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerGlobalAuditListeners();

        // Temporarily skip DB checks if tables don't exist
        try {
            if (!Schema::hasTable('security_settings')) {
                return;
            }
        } catch (\Exception $e) {
            return;
        }

        if (Schema::hasTable('security_settings')) {
            $securitySetting = SecuritySetting::query()->first();

            if ($securitySetting) {
                config([
                    'security.backup.path' => $securitySetting->backup_path,
                    'security.backup.retention_days' => (int) $securitySetting->backup_retention_days,
                    'security.backup.schedule_time' => (string) $securitySetting->backup_schedule_time,
                    'security.backup.compress' => (bool) $securitySetting->backup_compress,
                    'security.backup.timeout_seconds' => (int) $securitySetting->backup_timeout_seconds,
                    'security.backup.mysql_dump_binary' => $securitySetting->mysql_dump_binary ?: 'mysqldump',
                    'security.backup.pg_dump_binary' => $securitySetting->pg_dump_binary ?: 'pg_dump',
                    'security.audit.retention_days' => (int) $securitySetting->audit_retention_days,
                ]);
            }
        }



       $Ssitteng = SiteSetting::with(['media'])->where('id',1)->first();
        $orders = Order::with('sender', 'recipient','orderInsurances')->latest('created_at')->get();
        $social_links = SocialLink::get();

        // View::share('Ssitteng', $Ssitteng);
        // View::share('orders',$orders);
        // View::share('social_links',$orders);
        View::share(compact('Ssitteng','orders','social_links'));


    }

    private function registerGlobalAuditListeners(): void
    {
        Event::listen('eloquent.created: *', function (string $event, array $payload): void {
            $model = $payload[0] ?? null;

            $this->writeGlobalAudit('audit:created', $model, [
                'attributes' => $model ? $model->getAttributes() : [],
            ]);
        });

        Event::listen('eloquent.updated: *', function (string $event, array $payload): void {
            $model = $payload[0] ?? null;

            $this->writeGlobalAudit('audit:updated', $model, [
                'changes' => $model ? $model->getChanges() : [],
                'original' => $model ? $model->getOriginal() : [],
                'attributes' => $model ? $model->getAttributes() : [],
            ]);
        });

        Event::listen('eloquent.deleted: *', function (string $event, array $payload): void {
            $model = $payload[0] ?? null;

            $this->writeGlobalAudit('audit:deleted', $model, [
                'attributes' => $model ? $model->getAttributes() : [],
                'original' => $model ? $model->getOriginal() : [],
            ]);
        });

        Event::listen('eloquent.restored: *', function (string $event, array $payload): void {
            $model = $payload[0] ?? null;

            $this->writeGlobalAudit('audit:restored', $model, [
                'attributes' => $model ? $model->getAttributes() : [],
            ]);
        });
    }

    private function writeGlobalAudit(string $description, ?Model $model, array $payload = []): void
    {
        if (!$model || $model instanceof AuditLog || in_array(Auditable::class, class_uses_recursive($model), true)) {
            return;
        }

        if (app()->runningInConsole()) {
            return;
        }

        $request = request();

        AuditLog::createSafely([
            'event_uuid' => (string) Str::uuid(),
            'action' => $description,
            'description' => $description,
            'subject_id' => $model->getKey(),
            'subject_type' => get_class($model),
            'user_id' => auth()->id(),
            'properties' => [
                'event' => $description,
                'payload' => $payload,
                'request' => [
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                    'route' => $request->route() ? $request->route()->getName() : null,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ],
            ],
            'host' => $request->ip(),
            'request_method' => $request->method(),
            'request_url' => $request->fullUrl(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
