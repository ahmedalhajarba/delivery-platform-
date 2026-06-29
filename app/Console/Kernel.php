<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;


class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\ImportMbox::class,
        \App\Console\Commands\FixAdminRole::class,
        \App\Console\Commands\NormalizePermissionSectionsCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $backupCommand = 'security:backup-db' . (config('security.backup.compress', true) ? ' --compress' : '');

        $schedule->command($backupCommand)
            ->dailyAt((string) config('security.backup.schedule_time', '02:30'))
            ->withoutOverlapping();

        $schedule->command('model:prune --model=App\\Models\\AuditLog')
            ->dailyAt('03:30')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
