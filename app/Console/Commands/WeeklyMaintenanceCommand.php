<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WeeklyMaintenanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'maintenance:weekly 
                            {--dry-run : Show what would be done without executing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform weekly maintenance tasks';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = microtime(true);
        $isDryRun = $this->option('dry-run');

        $this->info('🔧 Starting weekly maintenance...');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No actual changes will be made');
        }

        try {
            $tasks = [
                'cleanup_old_logs' => $this->cleanupOldLogs($isDryRun),
                'cleanup_failed_jobs' => $this->cleanupFailedJobs($isDryRun),
                'optimize_tables' => $this->optimizeTables($isDryRun),
                'cleanup_old_cache' => $this->cleanupOldCache($isDryRun),
                'archive_old_data' => $this->archiveOldData($isDryRun),
                'update_statistics' => $this->updateStatistics($isDryRun),
            ];

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->newLine();
            $this->info('✅ Weekly maintenance completed!');
            $this->info("⏱️  Duration: {$duration}ms");
            $this->newLine();

            // Display task results
            $tableData = [];
            foreach ($tasks as $task => $result) {
                $status = $result['success'] ? '✅ Success' : '❌ Failed';
                $tableData[] = [
                    ucfirst(str_replace('_', ' ', $task)),
                    $status,
                    $result['message'],
                ];
            }

            $this->table(['Task', 'Status', 'Details'], $tableData);

            if ($isDryRun) {
                $this->warn('💡 This was a dry run. Run without --dry-run to execute maintenance.');
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Weekly maintenance failed: {$e->getMessage()}");
            Log::error('Weekly maintenance failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Clean up old log files
     */
    private function cleanupOldLogs(bool $isDryRun): array
    {
        try {
            $logPath = storage_path('logs');
            $cutoffDate = now()->subDays(30);
            $deletedFiles = 0;

            $this->info('📋 Cleaning up old log files...');

            if (is_dir($logPath)) {
                $files = glob($logPath.'/*.log');

                foreach ($files as $file) {
                    if (filemtime($file) < $cutoffDate->timestamp) {
                        if (! $isDryRun) {
                            unlink($file);
                        }
                        $deletedFiles++;
                    }
                }
            }

            return [
                'success' => true,
                'message' => "Deleted {$deletedFiles} old log files",
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Failed to cleanup logs: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Clean up old failed jobs
     */
    private function cleanupFailedJobs(bool $isDryRun): array
    {
        try {
            $cutoffDate = now()->subDays(7);

            if ($isDryRun) {
                $count = DB::table('failed_jobs')
                    ->where('failed_at', '<', $cutoffDate)
                    ->count();

                return [
                    'success' => true,
                    'message' => "Would delete {$count} old failed jobs",
                ];
            }

            $deleted = DB::table('failed_jobs')
                ->where('failed_at', '<', $cutoffDate)
                ->delete();

            return [
                'success' => true,
                'message' => "Deleted {$deleted} old failed jobs",
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Failed to cleanup failed jobs: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Optimize database tables
     */
    private function optimizeTables(bool $isDryRun): array
    {
        try {
            $tables = ['plans', 'failed_jobs', 'jobs', 'cache'];
            $optimizedTables = [];

            foreach ($tables as $table) {
                if ($isDryRun) {
                    $optimizedTables[] = $table;

                    continue;
                }

                try {
                    DB::statement("OPTIMIZE TABLE `{$table}`");
                    $optimizedTables[] = $table;
                } catch (\Exception $e) {
                    // Table might not exist, continue
                    Log::warning("Could not optimize table {$table}: {$e->getMessage()}");
                }
            }

            return [
                'success' => true,
                'message' => 'Optimized '.implode(', ', $optimizedTables).' tables',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Failed to optimize tables: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Clean up old cache entries
     */
    private function cleanupOldCache(bool $isDryRun): array
    {
        try {
            // This would integrate with your cache cleanup command
            if ($isDryRun) {
                return [
                    'success' => true,
                    'message' => 'Would run cache cleanup command',
                ];
            }

            $this->call('cache:cleanup', ['--dry-run' => false]);

            return [
                'success' => true,
                'message' => 'Cache cleanup completed',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Failed to cleanup cache: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Archive old data
     */
    private function archiveOldData(bool $isDryRun): array
    {
        try {
            $cutoffDate = now()->subYear();
            $archivedCount = 0;

            if ($isDryRun) {
                $count = DB::table('plans')
                    ->where('ends_at', '<', $cutoffDate)
                    ->count();

                return [
                    'success' => true,
                    'message' => "Would archive {$count} old plan records",
                ];
            }

            // Archive old plans (move to archive table or delete if not needed)
            $archivedCount = DB::table('plans')
                ->where('ends_at', '<', $cutoffDate)
                ->delete();

            return [
                'success' => true,
                'message' => "Archived {$archivedCount} old plan records",
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Failed to archive old data: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Update system statistics
     */
    private function updateStatistics(bool $isDryRun): array
    {
        try {
            if ($isDryRun) {
                return [
                    'success' => true,
                    'message' => 'Would update system statistics',
                ];
            }

            // Update various statistics
            $stats = [
                'total_plans' => DB::table('plans')->count(),
                'active_plans' => DB::table('plans')
                    ->where('starts_at', '<=', now())
                    ->where('ends_at', '>=', now())
                    ->count(),
                'failed_jobs_count' => DB::table('failed_jobs')->count(),
                'pending_jobs_count' => DB::table('jobs')->count(),
            ];

            // Store stats in cache for dashboard
            cache()->put('system_stats', $stats, now()->addDay());

            return [
                'success' => true,
                'message' => 'Updated system statistics: '.json_encode($stats),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Failed to update statistics: {$e->getMessage()}",
            ];
        }
    }
}
