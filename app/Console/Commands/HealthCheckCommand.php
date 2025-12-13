<?php

namespace App\Console\Commands;

use App\Services\CircuitBreakerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'health:check 
                            {--detailed : Show detailed health information}
                            {--alert : Send alerts if issues found}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check system health and performance metrics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $detailed = $this->option('detailed');
        $shouldAlert = $this->option('alert');

        $this->info('🏥 Starting system health check...');

        $healthStatus = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'provider_api' => $this->checkProviderAPI(),
            'circuit_breaker' => $this->checkCircuitBreaker(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'disk_space' => $this->checkDiskSpace(),
            'memory' => $this->checkMemory(),
        ];

        $overallHealth = $this->calculateOverallHealth($healthStatus);

        $this->displayHealthReport($healthStatus, $overallHealth, $detailed);

        if ($shouldAlert && $overallHealth['status'] !== 'healthy') {
            $this->sendAlerts($healthStatus, $overallHealth);
        }

        return $overallHealth['status'] === 'healthy' ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Check database connectivity
     */
    private function checkDatabase(): array
    {
        try {
            $startTime = microtime(true);
            DB::select('SELECT 1');
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            $connectionCount = DB::select('SHOW STATUS LIKE "Threads_connected"')[0]->Value ?? 0;

            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'connections' => $connectionCount,
                'message' => 'Database responding normally',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Database connection failed',
            ];
        }
    }

    /**
     * Check Redis connectivity
     */
    private function checkRedis(): array
    {
        try {
            $startTime = microtime(true);
            \Illuminate\Support\Facades\Redis::ping();
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            $info = \Illuminate\Support\Facades\Redis::info();
            $memoryUsage = $info['used_memory'] ?? 0;
            $connectedClients = $info['connected_clients'] ?? 0;

            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'memory_usage_bytes' => $memoryUsage,
                'connected_clients' => $connectedClients,
                'message' => 'Redis responding normally',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Redis connection failed',
            ];
        }
    }

    /**
     * Check provider API health
     */
    private function checkProviderAPI(): array
    {
        try {
            $circuitBreaker = app(CircuitBreakerService::class);

            $circuitState = $circuitBreaker->getState('provider_api');

            // Simple health check based on circuit breaker state
            $isHealthy = $circuitState !== 'open';

            return [
                'status' => $isHealthy ? 'healthy' : 'unhealthy',
                'circuit_breaker_state' => $circuitState,
                'response_time_ms' => 0, // Not measured in simple check
                'message' => $isHealthy ? 'Provider API responding' : 'Provider API circuit breaker open',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Provider API health check failed',
            ];
        }
    }

    /**
     * Check circuit breaker status
     */
    private function checkCircuitBreaker(): array
    {
        try {
            $circuitBreaker = app(CircuitBreakerService::class);
            $state = $circuitBreaker->getState('provider_api');
            $stats = $circuitBreaker->getStats('provider_api');

            $status = 'healthy';
            if ($state === 'open') {
                $status = 'unhealthy';
            } elseif ($stats['failure_rate'] > 50) {
                $status = 'warning';
            }

            return [
                'status' => $status,
                'state' => $state,
                'failure_rate' => $stats['failure_rate'],
                'success_count' => $stats['success_count'],
                'failure_count' => $stats['failure_count'],
                'message' => "Circuit breaker is {$state}",
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Circuit breaker check failed',
            ];
        }
    }

    /**
     * Check cache performance
     */
    private function checkCache(): array
    {
        try {
            // Test cache write/read
            $testKey = 'health_check_'.time();
            $testValue = ['test' => true, 'timestamp' => time()];

            $startTime = microtime(true);
            \Illuminate\Support\Facades\Cache::put($testKey, $testValue, 60);
            $writeTime = round((microtime(true) - $startTime) * 1000, 2);

            $startTime = microtime(true);
            $retrieved = \Illuminate\Support\Facades\Cache::get($testKey);
            $readTime = round((microtime(true) - $startTime) * 1000, 2);

            // Cleanup
            \Illuminate\Support\Facades\Cache::forget($testKey);

            $isHealthy = ($retrieved && $retrieved['test'] === true) &&
                        ($writeTime < 100) && ($readTime < 50);

            return [
                'status' => $isHealthy ? 'healthy' : 'unhealthy',
                'write_time_ms' => $writeTime,
                'read_time_ms' => $readTime,
                'message' => $isHealthy ? 'Cache performing normally' : 'Cache performance issues',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Cache check failed',
            ];
        }
    }

    /**
     * Check queue system
     */
    private function checkQueue(): array
    {
        try {
            $failedJobs = DB::table('failed_jobs')->count();
            $pendingJobs = DB::table('jobs')->count();

            $status = 'healthy';
            if ($failedJobs > 10) {
                $status = 'warning';
            }
            if ($failedJobs > 50) {
                $status = 'unhealthy';
            }

            return [
                'status' => $status,
                'pending_jobs' => $pendingJobs,
                'failed_jobs' => $failedJobs,
                'message' => "Queue status: {$status}",
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Queue check failed',
            ];
        }
    }

    /**
     * Check disk space
     */
    private function checkDiskSpace(): array
    {
        try {
            $totalSpace = disk_total_space('/');
            $freeSpace = disk_free_space('/');
            $usedSpace = $totalSpace - $freeSpace;
            $usagePercent = round(($usedSpace / $totalSpace) * 100, 2);

            $status = 'healthy';
            if ($usagePercent > 80) {
                $status = 'warning';
            }
            if ($usagePercent > 90) {
                $status = 'unhealthy';
            }

            return [
                'status' => $status,
                'total_gb' => round($totalSpace / 1024 / 1024 / 1024, 2),
                'free_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
                'usage_percent' => $usagePercent,
                'message' => "Disk usage: {$usagePercent}%",
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Disk space check failed',
            ];
        }
    }

    /**
     * Check memory usage
     */
    private function checkMemory(): array
    {
        try {
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
            $usagePercent = $memoryLimit > 0 ? round(($memoryUsage / $memoryLimit) * 100, 2) : 0;

            $status = 'healthy';
            if ($usagePercent > 80) {
                $status = 'warning';
            }
            if ($usagePercent > 90) {
                $status = 'unhealthy';
            }

            return [
                'status' => $status,
                'usage_mb' => round($memoryUsage / 1024 / 1024, 2),
                'limit_mb' => round($memoryLimit / 1024 / 1024, 2),
                'usage_percent' => $usagePercent,
                'message' => "Memory usage: {$usagePercent}%",
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Memory check failed',
            ];
        }
    }

    /**
     * Calculate overall system health
     */
    private function calculateOverallHealth(array $healthStatus): array
    {
        $healthyCount = 0;
        $warningCount = 0;
        $unhealthyCount = 0;

        foreach ($healthStatus as $component => $status) {
            switch ($status['status']) {
                case 'healthy':
                    $healthyCount++;
                    break;
                case 'warning':
                    $warningCount++;
                    break;
                case 'unhealthy':
                    $unhealthyCount++;
                    break;
            }
        }

        $overallStatus = 'healthy';
        if ($unhealthyCount > 0) {
            $overallStatus = 'unhealthy';
        } elseif ($warningCount > 0) {
            $overallStatus = 'warning';
        }

        return [
            'status' => $overallStatus,
            'healthy_components' => $healthyCount,
            'warning_components' => $warningCount,
            'unhealthy_components' => $unhealthyCount,
            'total_components' => count($healthStatus),
        ];
    }

    /**
     * Display health report
     */
    private function displayHealthReport(array $healthStatus, array $overallHealth, bool $detailed): void
    {
        $this->newLine();

        // Overall status
        $statusIcon = match ($overallHealth['status']) {
            'healthy' => '✅',
            'warning' => '⚠️',
            'unhealthy' => '❌',
            default => '❓',
        };

        $this->info("{$statusIcon} Overall System Status: {$overallHealth['status']}");
        $this->info("📊 {$overallHealth['healthy_components']} healthy, {$overallHealth['warning_components']} warnings, {$overallHealth['unhealthy_components']} unhealthy");

        $this->newLine();

        // Component status
        $tableData = [];
        foreach ($healthStatus as $component => $status) {
            $icon = match ($status['status']) {
                'healthy' => '✅',
                'warning' => '⚠️',
                'unhealthy' => '❌',
                default => '❓',
            };

            $tableData[] = [
                $icon.' '.ucfirst(str_replace('_', ' ', $component)),
                $status['status'],
                $status['message'],
            ];

            if ($detailed && isset($status['error'])) {
                $tableData[] = ['', 'Error', $status['error']];
            }
        }

        $this->table(['Component', 'Status', 'Message'], $tableData);
    }

    /**
     * Send alerts for unhealthy components
     */
    private function sendAlerts(array $healthStatus, array $overallHealth): void
    {
        $unhealthyComponents = array_filter($healthStatus, fn ($status) => $status['status'] === 'unhealthy');

        if (! empty($unhealthyComponents)) {
            $message = "🚨 System Health Alert\n\n";
            $message .= "Overall Status: {$overallHealth['status']}\n\n";
            $message .= "Unhealthy Components:\n";

            foreach ($unhealthyComponents as $component => $status) {
                $message .= '- '.ucfirst(str_replace('_', ' ', $component)).": {$status['message']}\n";
            }

            // Log the alert
            logger()->error($message);

            $this->error('🚨 Health alerts sent! Check logs for details.');
        }
    }

    /**
     * Parse PHP memory limit string to bytes
     */
    private function parseMemoryLimit(string $limit): int
    {
        $limit = strtolower(trim($limit));

        if ($limit === '-1') {
            return -1; // Unlimited
        }

        $multiplier = 1;
        if (str_ends_with($limit, 'g')) {
            $multiplier = 1024 * 1024 * 1024;
        } elseif (str_ends_with($limit, 'm')) {
            $multiplier = 1024 * 1024;
        } elseif (str_ends_with($limit, 'k')) {
            $multiplier = 1024;
        }

        return (int) $limit * $multiplier;
    }
}
