<?php

namespace App\Console\Commands;

use App\Jobs\PreloadHotDataJob;
use Illuminate\Console\Command;

class PreloadHotDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:preload-hot 
                            {--force : Force preload even if recently done}
                            {--date=today : Target date for preloading (today, tomorrow, weekend)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Preload frequently accessed data into cache';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = microtime(true);
        $force = $this->option('force');
        $targetDate = $this->option('date');

        $this->info('🔥 Starting hot data preload...');

        if ($force) {
            $this->info('Force preload enabled');
        }

        $this->info("Target date: {$targetDate}");

        try {
            // Check if preload was done recently (unless forced)
            if (! $force && $this->wasRecentlyPreloaded()) {
                $this->warn('Hot data was preloaded recently. Use --force to override.');
                $this->info('Skipping preload.');

                return self::SUCCESS;
            }

            // Calculate date ranges for preloading
            $dateRanges = $this->getPreloadDateRanges($targetDate);

            $this->info('📅 Preloading data for date ranges:');
            foreach ($dateRanges as $range) {
                $this->info("  - {$range['starts_at']} to {$range['ends_at']}");
            }

            $totalJobs = 0;
            $progressBar = $this->output->createProgressBar(count($dateRanges));
            $progressBar->start();

            foreach ($dateRanges as $range) {
                // Dispatch preload job for each date range
                PreloadHotDataJob::dispatch($range['starts_at'], $range['ends_at']);
                $totalJobs++;
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();

            // Mark preload as done
            cache()->put('last_hot_preload', now(), now()->addHours(2));

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $this->newLine();
            $this->info('✅ Hot data preload completed!');
            $this->info("📋 Jobs dispatched: {$totalJobs}");
            $this->info("⏱️  Dispatch time: {$duration}ms");

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Hot data preload failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Check if hot data was preloaded recently (within last hour)
     */
    private function wasRecentlyPreloaded(): bool
    {
        $lastPreload = cache()->get('last_hot_preload');

        if (! $lastPreload) {
            return false;
        }

        $oneHourAgo = now()->subHour();

        return $lastPreload->gt($oneHourAgo);
    }

    /**
     * Get date ranges for preloading based on target date
     */
    private function getPreloadDateRanges(string $targetDate): array
    {
        $now = now();

        return match ($targetDate) {
            'today' => [
                [
                    'starts_at' => $now->format('Y-m-d'),
                    'ends_at' => $now->format('Y-m-d'),
                ],
                [
                    'starts_at' => $now->copy()->addDay()->format('Y-m-d'),
                    'ends_at' => $now->copy()->addDay()->format('Y-m-d'),
                ],
            ],
            'tomorrow' => [
                [
                    'starts_at' => $now->copy()->addDay()->format('Y-m-d'),
                    'ends_at' => $now->copy()->addDay()->format('Y-m-d'),
                ],
                [
                    'starts_at' => $now->copy()->addDays(2)->format('Y-m-d'),
                    'ends_at' => $now->copy()->addDays(2)->format('Y-m-d'),
                ],
            ],
            'weekend' => [
                [
                    'starts_at' => $now->startOfWeek()->addDays(5)->format('Y-m-d'), // Saturday
                    'ends_at' => $now->startOfWeek()->addDays(6)->format('Y-m-d'),   // Sunday
                ],
                [
                    'starts_at' => $now->startOfWeek()->addDays(7)->format('Y-m-d'), // Next Saturday
                    'ends_at' => $now->startOfWeek()->addDays(8)->format('Y-m-d'), // Next Sunday
                ],
            ],
            default => [
                [
                    'starts_at' => $now->format('Y-m-d'),
                    'ends_at' => $now->copy()->addDays(7)->format('Y-m-d'),
                ],
            ],
        };
    }
}
