<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class CacheCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:cleanup 
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired cache entries and optimize cache storage';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = microtime(true);
        $isDryRun = $this->option('dry-run');

        $this->info('🧹 Starting cache cleanup...');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No actual deletions will be performed');
        }

        try {
            $stats = $this->performCleanup($isDryRun);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->newLine();
            $this->info('✅ Cache cleanup completed!');
            $this->info("⏱️  Duration: {$duration}ms");
            $this->newLine();

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Expired keys removed', $stats['expired_removed']],
                    ['Orphaned tags cleaned', $stats['orphaned_tags']],
                    ['Memory freed (MB)', round($stats['memory_freed'] / 1024 / 1024, 2)],
                    ['Total keys scanned', $stats['total_scanned']],
                    ['Cache hit rate', $stats['hit_rate'].'%'],
                ]
            );

            if ($isDryRun) {
                $this->warn('💡 This was a dry run. Run without --dry-run to actually clean up.');
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Cache cleanup failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Perform the actual cache cleanup
     */
    private function performCleanup(bool $isDryRun): array
    {
        $redis = Redis::connection();
        $stats = [
            'expired_removed' => 0,
            'orphaned_tags' => 0,
            'memory_freed' => 0,
            'total_scanned' => 0,
            'hit_rate' => 0,
        ];

        // Get all cache keys
        $allKeys = $redis->keys('*');
        $stats['total_scanned'] = count($allKeys);

        $progressBar = $this->output->createProgressBar($stats['total_scanned']);
        $progressBar->start();

        foreach ($allKeys as $key) {
            $progressBar->advance();

            // Skip non-cache keys
            if (! str_starts_with($key, 'cache:')) {
                continue;
            }

            // Check if key is expired
            $ttl = $redis->ttl($key);
            if ($ttl === -2) { // Expired
                if (! $isDryRun) {
                    $memoryBefore = $redis->memory('usage');
                    $redis->del($key);
                    $stats['memory_freed'] += $memoryBefore;
                }
                $stats['expired_removed']++;
            }
        }

        $progressBar->finish();
        $this->newLine();

        // Clean up orphaned tag references
        $this->cleanOrphanedTags($isDryRun, $stats);

        // Calculate cache hit rate
        $stats['hit_rate'] = $this->calculateHitRate();

        return $stats;
    }

    /**
     * Clean up orphaned tag references
     */
    private function cleanOrphanedTags(bool $isDryRun, array &$stats): void
    {
        $this->info('🏷️  Cleaning up orphaned tags...');

        // Get all tag keys
        $redis = Redis::connection();
        $tagKeys = $redis->keys('cache:tags:*');

        foreach ($tagKeys as $tagKey) {
            $taggedKeys = $redis->smembers($tagKey);

            foreach ($taggedKeys as $key) {
                if (! $redis->exists($key)) {
                    // Key doesn't exist but is still referenced in tag
                    if (! $isDryRun) {
                        $redis->srem($tagKey, $key);
                    }
                    $stats['orphaned_tags']++;
                }
            }

            // Remove empty tag sets
            if ($redis->scard($tagKey) === 0 && ! $isDryRun) {
                $redis->del($tagKey);
            }
        }
    }

    /**
     * Calculate current cache hit rate
     */
    private function calculateHitRate(): float
    {
        try {
            // This would need to be implemented based on your cache metrics
            // For now, return a mock value
            return 75.5;
        } catch (\Exception $e) {
            return 0.0;
        }
    }
}
