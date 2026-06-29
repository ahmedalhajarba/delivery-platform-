<?php

namespace App\Providers;

use App\Events\OrderProcessed;
use App\Listeners\SendOrderNotification;
use App\Models\Order;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        Order::class => [
            SendOrderNotification::class,

        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        Event::listen(
            OrderProcessed::class,
            [SendOrderNotification::class, 'handle']
        );

        Event::listen(function (OrderProcessed $event) {
            //
        });

        // تسجيل شامل لأحداث النماذج عبر Observer
        // في Laravel 8، نستخدم Model::observe() لكل نموذج
        // أو نسجل الأحداث العامة للنماذج
        $modelListener = \App\Listeners\LogModelChanges::class;
        
        // الاستماع إلى أحداث النماذج الخام
        Event::listen('eloquent.created: *', [$modelListener, 'onCreated']);
        Event::listen('eloquent.updated: *', [$modelListener, 'onUpdated']);
        Event::listen('eloquent.deleted: *', [$modelListener, 'onDeleted']);
        Event::listen('eloquent.restored: *', [$modelListener, 'onRestored']);
    }
}
