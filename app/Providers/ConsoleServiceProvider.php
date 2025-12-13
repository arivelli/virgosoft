<?php

namespace App\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class ConsoleServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $this->schedule($schedule);
            });
        }
    }

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Sync plans every hour during business hours (9 AM - 9 PM)
        $schedule->command('sync:plans --scheduled')
            ->hourly()
            ->between('9:00', '21:00')
            ->description('Sync plans from provider (business hours)')
            ->onSuccess(function () {
                logger()->info('Scheduled sync completed successfully');
            })
            ->onFailure(function () {
                logger()->error('Scheduled sync failed');
            });

        // Full sync every 6 hours (including overnight)
        $schedule->command('sync:plans --force --scheduled')
            ->cron('0 */6 * * *') // Every 6 hours at minute 0
            ->description('Full force sync every 6 hours')
            ->onSuccess(function () {
                logger()->info('Scheduled full sync completed successfully');
            })
            ->onFailure(function () {
                logger()->error('Scheduled full sync failed');
            });

        // Cache cleanup and optimization daily at 2 AM
        $schedule->command('cache:cleanup')
            ->dailyAt('02:00')
            ->description('Clean up expired cache entries')
            ->onSuccess(function () {
                logger()->info('Cache cleanup completed successfully');
            })
            ->onFailure(function () {
                logger()->error('Cache cleanup failed');
            });

        // Health check every 5 minutes
        $schedule->command('health:check')
            ->everyFiveMinutes()
            ->description('Check system health and metrics')
            ->onFailure(function () {
                logger()->error('Health check failed - system may be down');
            });

        // Preload hot data every 30 minutes
        $schedule->command('cache:preload-hot')
            ->everyThirtyMinutes()
            ->description('Preload frequently accessed data')
            ->onSuccess(function () {
                logger()->info('Hot data preload completed successfully');
            })
            ->onFailure(function () {
                logger()->error('Hot data preload failed');
            });

        // Weekly maintenance on Sundays at 3 AM
        $schedule->command('maintenance:weekly')
            ->weeklyOn(0, '03:00') // Sunday at 3 AM
            ->description('Weekly maintenance tasks')
            ->onSuccess(function () {
                logger()->info('Weekly maintenance completed successfully');
            })
            ->onFailure(function () {
                logger()->error('Weekly maintenance failed');
            });
    }
}
