<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Quick sync command for development
Artisan::command('sync:now', function () {
    $this->call('sync:plans', ['--force' => true]);
})->describe('Quick force sync of plans');

// Quick health check
Artisan::command('health:quick', function () {
    $this->call('health:check', ['--detailed' => false]);
})->describe('Quick health check without details');

// Emergency cache clear
Artisan::command('cache:emergency-clear', function () {
    $this->warn('⚠️  Emergency cache clear - this will clear ALL cache!');
    if ($this->confirm('Are you sure you want to continue?')) {
        $this->call('cache:clear');
        $this->info('✅ All cache cleared');
    } else {
        $this->info('❌ Cache clear cancelled');
    }
})->describe('Emergency clear all cache data');

// System status overview
Artisan::command('system:status', function () {
    $this->info('🏥 System Status Overview');
    $this->newLine();

    // Health check
    $this->call('health:check', ['--detailed' => false]);
    $this->newLine();

    // Recent sync status
    $lastSync = cache()->get('last_plans_sync');
    if ($lastSync) {
        $this->info("📅 Last sync: {$lastSync->diffForHumans()}");
    } else {
        $this->warn('⚠️  No recent sync found');
    }

    // Cache status
    $this->call('cache:cleanup', ['--dry-run' => true]);
})->describe('Show complete system status overview');
