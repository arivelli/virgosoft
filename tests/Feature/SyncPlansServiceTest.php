<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Services\ProviderService;
use App\Services\SyncPlansService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncPlansServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Cache::flush();

        // Fake queues to avoid actual job execution
        Queue::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_can_synchronize_plans_successfully()
    {
        // Arrange
        $mockPlansData = [
            [
                'provider_id' => 'test-plan-1',
                'title' => 'Test Plan 1',
                'sell_mode' => 'online',
                'starts_at' => now()->addDay(),
                'ends_at' => now()->addDays(2),
            ],
            [
                'provider_id' => 'test-plan-2',
                'title' => 'Test Plan 2',
                'sell_mode' => 'offline',
                'starts_at' => now()->addDays(3),
                'ends_at' => now()->addDays(4),
            ],
        ];

        $mockProviderService = Mockery::mock(ProviderService::class);
        $mockProviderService->shouldReceive('fetchPlans')->once()->andReturn($mockPlansData);
        $this->app->instance(ProviderService::class, $mockProviderService);

        // Act
        $result = SyncPlansService::synchronize([
            'force_sync' => true,
            'is_async' => false,
        ]);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['total_processed']);
        $this->assertEquals(2, $result['total_created']);
        $this->assertEquals(0, $result['total_updated']);
        $this->assertArrayHasKey('duration_ms', $result);

        // Verify database records
        $this->assertDatabaseCount('plans', 2);
        $this->assertDatabaseHas('plans', [
            'provider_id' => 'test-plan-1',
            'title' => 'Test Plan 1',
        ]);
        $this->assertDatabaseHas('plans', [
            'provider_id' => 'test-plan-2',
            'title' => 'Test Plan 2',
        ]);

        // Verify sync time was recorded
        $this->assertNotNull(Cache::get('last_plans_sync'));
    }

    #[Test]
    public function it_can_update_existing_plans()
    {
        // Arrange
        Plan::create([
            'provider_id' => 'existing-plan',
            'title' => 'Old Title',
            'sell_mode' => 'online',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        $mockPlansData = [
            [
                'provider_id' => 'existing-plan',
                'title' => 'Updated Title',
                'sell_mode' => 'online',
                'starts_at' => now()->addDay(),
                'ends_at' => now()->addDays(2),
            ],
        ];

        $mockProviderService = Mockery::mock(ProviderService::class);
        $mockProviderService->shouldReceive('fetchPlans')->once()->andReturn($mockPlansData);
        $this->app->instance(ProviderService::class, $mockProviderService);

        // Act
        $result = SyncPlansService::synchronize([
            'force_sync' => true,
            'is_async' => false,
        ]);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['total_processed']);
        $this->assertEquals(0, $result['total_created']);
        $this->assertEquals(1, $result['total_updated']);

        // Verify the plan was updated
        $this->assertDatabaseCount('plans', 1);
        $this->assertDatabaseHas('plans', [
            'provider_id' => 'existing-plan',
            'title' => 'Updated Title',
        ]);
    }

    #[Test]
    public function it_handles_empty_plans_data()
    {
        // Arrange
        $mockProviderService = Mockery::mock(ProviderService::class);
        $mockProviderService->shouldReceive('fetchPlans')->once()->andReturn([]);
        $this->app->instance(ProviderService::class, $mockProviderService);

        // Act
        $result = SyncPlansService::synchronize([
            'force_sync' => true,
            'is_async' => false,
        ]);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['total_processed']);
        $this->assertEquals(0, $result['total_created']);
        $this->assertEquals(0, $result['total_updated']);
        $this->assertEquals('No plans found from provider', $result['message']);

        // Verify no records were created
        $this->assertDatabaseCount('plans', 0);
    }

    #[Test]
    public function it_handles_provider_service_failure()
    {
        // Arrange
        $mockProviderService = Mockery::mock(ProviderService::class);
        $mockProviderService->shouldReceive('fetchPlans')->once()->andThrow(
            new \Exception('Provider API unavailable')
        );
        $this->app->instance(ProviderService::class, $mockProviderService);

        // Act
        $result = SyncPlansService::synchronize([
            'force_sync' => true,
            'is_async' => false,
        ]);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('Provider API unavailable', $result['error']);
        $this->assertArrayHasKey('duration_ms', $result);
    }

    #[Test]
    public function it_detects_recent_sync()
    {
        // Arrange
        Cache::put('last_plans_sync', now()->subMinutes(30), now()->addDays(7));

        // Act
        $wasRecentlySynced = SyncPlansService::wasRecentlySynced();

        // Assert
        $this->assertTrue($wasRecentlySynced);
    }

    #[Test]
    public function it_detects_sync_needed_when_no_recent_sync()
    {
        // Arrange - No cache entry or old cache
        Cache::put('last_plans_sync', now()->subHours(2), now()->addDays(7));

        // Act
        $wasRecentlySynced = SyncPlansService::wasRecentlySynced();

        // Assert
        $this->assertFalse($wasRecentlySynced);
    }

    #[Test]
    public function it_gets_sync_statistics()
    {
        // Arrange
        Cache::put('last_plans_sync', now()->subHours(1), now()->addDays(7));

        Plan::create([
            'provider_id' => 'active-plan',
            'title' => 'Active Plan',
            'sell_mode' => 'online',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        Plan::create([
            'provider_id' => 'expired-plan',
            'title' => 'Expired Plan',
            'sell_mode' => 'offline',
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);

        // Act
        $stats = SyncPlansService::getSyncStats();

        // Assert
        $this->assertArrayHasKey('last_sync', $stats);
        $this->assertArrayHasKey('last_sync_at', $stats);
        $this->assertArrayHasKey('total_plans', $stats);
        $this->assertArrayHasKey('active_plans', $stats);
        $this->assertArrayHasKey('sync_needed', $stats);

        $this->assertEquals(2, $stats['total_plans']);
        $this->assertEquals(1, $stats['active_plans']);
        $this->assertTrue($stats['sync_needed']); // Should need sync since we didn't actually sync in this test
    }

    #[Test]
    public function it_handles_async_context_correctly()
    {
        // Arrange
        $mockPlansData = [
            [
                'provider_id' => 'async-plan',
                'title' => 'Async Plan',
                'sell_mode' => 'online',
                'starts_at' => now()->addDay(),
                'ends_at' => now()->addDays(2),
            ],
        ];

        $mockProviderService = Mockery::mock(ProviderService::class);
        $mockProviderService->shouldReceive('fetchPlans')->once()->andReturn($mockPlansData);
        $this->app->instance(ProviderService::class, $mockProviderService);

        // Act
        $result = SyncPlansService::synchronize([
            'force_sync' => true,
            'is_async' => true,
            'job_id' => 'test-job-123',
        ]);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['total_processed']);

        // Verify database record was created
        $this->assertDatabaseCount('plans', 1);
        $this->assertDatabaseHas('plans', ['provider_id' => 'async-plan']);
    }
}
