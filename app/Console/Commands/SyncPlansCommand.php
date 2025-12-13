<?php

namespace App\Console\Commands;

use App\Jobs\SyncPlansFromProvider;
use App\Services\SyncPlansService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncPlansCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:plans 
                            {--force : Force synchronization even if recently synced}
                            {--provider-id= : Sync specific provider only}
                            {--scheduled : Indicates this is a scheduled run}
                            {--dry-run : Show what would be done without actually syncing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync plans from provider';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = microtime(true);
        $force = $this->option('force');
        $providerId = $this->option('provider-id');
        $isScheduled = $this->option('scheduled');
        $isDryRun = $this->option('dry-run');

        $this->info('Starting plan synchronization...');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No actual synchronization will be performed');
        }

        if ($isScheduled) {
            $this->info('Running as scheduled task');
        }

        if ($force) {
            $this->info('Force sync enabled');
        }

        if ($providerId) {
            $this->info("Syncing provider: {$providerId}");
        }

        try {
            // Check if we should skip due to recent sync (unless forced)
            if (! $force && ! $isScheduled && SyncPlansService::wasRecentlySynced()) {
                $this->warn('Plans were synced recently. Use --force to override.');
                $this->info('Skipping synchronization.');

                return self::SUCCESS;
            }

            if ($isDryRun) {
                $this->info('✅ Would dispatch sync job with the following parameters:');
                $this->info('  - Force: '.($force ? 'Yes' : 'No'));
                $this->info('  - Provider ID: '.($providerId ?? 'All'));
                $this->info('  - Scheduled: '.($isScheduled ? 'Yes' : 'No'));

                return self::SUCCESS;
            }

            // Dispatch async job for better performance
            $job = new SyncPlansFromProvider(
                forceSync: $force,
                providerId: $providerId
            );

            dispatch($job);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->info('✅ Sync job dispatched successfully!');
            $this->info("⏱️  Dispatch time: {$duration}ms");

            Log::info('Plans sync command executed', [
                'command' => 'sync:plans',
                'force' => $force,
                'provider_id' => $providerId,
                'scheduled' => $isScheduled,
                'duration_ms' => $duration,
            ]);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->error("❌ Sync failed: {$e->getMessage()}");
            $this->error("⏱️  Failed after: {$duration}ms");

            Log::error('Plans sync command failed', [
                'command' => 'sync:plans',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => $duration,
            ]);

            return self::FAILURE;
        }
    }
}
