<?php

namespace Tests\Feature;

use App\Services\CircuitBreakerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ToleranceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear datos de prueba
        $this->seed(\Database\Seeders\PlanSeeder::class);
    }

    /**
     * Test search works when external API fails
     */
    public function test_search_works_when_external_api_fails(): void
    {
        // Mock external API to fail
        Http::fake([
            'provider.code-challenge.feverup.com/api/events' => Http::response('', 503),
        ]);

        // Force circuit breaker to simulate failure
        CircuitBreakerService::forceState('provider_api', 'OPEN');

        // Search should still work with local data
        $response = $this->getJson('/api/plans?starts_at=2024-01-01&ends_at=2024-06-30');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.title', 'Taylor Swift Concert');
    }

    /**
     * Test fallback mechanism activates
     */
    public function test_fallback_mechanism_activates(): void
    {
        // Force circuit breaker open to trigger fallback
        CircuitBreakerService::forceState('provider_api', 'OPEN');

        $response = $this->postJson('/api/plans/sync', ['force' => true]);

        // Should handle failure gracefully with 200 status
        $response->assertStatus(200)
            ->assertJsonPath('plans_synced', 0)
            ->assertJsonPath('error_type', 'provider_failure');
    }

    /**
     * Test timeout scenarios
     */
    public function test_timeout_handling(): void
    {
        // Mock provider to timeout
        Http::fake([
            'provider.code-challenge.feverup.com/api/events' => Http::response('', 503),
        ]);

        $response = $this->postJson('/api/plans/sync', []);

        // Should handle timeout gracefully
        $response->assertStatus(200)
            ->assertJsonPath('plans_synced', 0);
    }

    /**
     * Test concurrent requests under failure
     */
    public function test_concurrent_requests_under_failure(): void
    {
        // Force circuit breaker open
        CircuitBreakerService::forceState('provider_api', 'OPEN');

        // Multiple concurrent requests should all work
        $responses = [];
        for ($i = 0; $i < 10; $i++) {
            $responses[] = $this->getJson('/api/plans?starts_at=2024-01-01&ends_at=2024-06-30');
        }

        foreach ($responses as $response) {
            $response->assertStatus(200)
                ->assertJsonPath('meta.total', 2);
        }
    }

    /**
     * Test graceful degradation
     */
    public function test_graceful_degradation(): void
    {
        // Force external service failure
        CircuitBreakerService::forceState('provider_api', 'OPEN');

        // All endpoints should still respond appropriately
        $searchResponse = $this->getJson('/api/plans?starts_at=2024-01-01&ends_at=2024-06-30');
        $healthResponse = $this->getJson('/api/health');
        $syncResponse = $this->postJson('/api/plans/sync', []);

        $searchResponse->assertStatus(200);
        $healthResponse->assertStatus(200);
        $syncResponse->assertStatus(200);

        // Search should work, sync should fail gracefully, health should show degraded
        $this->assertEquals(2, $searchResponse->json('meta.total'));
        $this->assertEquals('degraded', $healthResponse->json('status'));
        $this->assertEquals(0, $syncResponse->json('plans_synced'));
    }
}
