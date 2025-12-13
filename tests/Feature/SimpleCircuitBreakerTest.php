<?php

namespace Tests\Feature;

use App\Services\CircuitBreakerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpleCircuitBreakerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlanSeeder::class);
    }

    /**
     * Test básico: la búsqueda funciona sin importar el circuit breaker
     */
    public function test_basic_search_works(): void
    {
        // Copiado exactamente de PlanApiTest
        $response = $this->getJson('/api/plans?starts_at=2024-01-01&ends_at=2024-06-30');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'provider_id',
                        'title',
                        'description',
                        'sell_mode',
                        'starts_at',
                        'ends_at',
                        'zones',
                    ],
                ],
                'meta' => [
                    'total',
                    'limit',
                    'offset',
                    'has_more',
                ],
                'performance' => [
                    'duration_ms',
                    'cached',
                ],
            ]);

        // Debería encontrar 2 planes en el rango (Taylor Swift y Food & Wine Festival)
        $response->assertJsonPath('meta.total', 2);
        $response->assertJsonPath('data.0.title', 'Taylor Swift Concert');
        $response->assertJsonPath('data.1.title', 'Food & Wine Festival');
    }

    /**
     * Test circuit breaker no afecta búsqueda local
     */
    public function test_circuit_breaker_does_not_affect_local_search(): void
    {
        // Forzar circuit breaker open
        CircuitBreakerService::forceState('provider_api', 'OPEN');

        // La búsqueda local DEBE seguir funcionando (mismo rango que el test original)
        $response = $this->getJson('/api/plans?starts_at=2024-01-01&ends_at=2024-06-30');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2); // Debe encontrar los mismos 2 planes
    }

    /**
     * Test sync falla gracefulmente con circuit breaker open
     */
    public function test_sync_fails_gracefully_with_circuit_open(): void
    {
        // Forzar circuit breaker open
        CircuitBreakerService::forceState('provider_api', 'OPEN');

        $response = $this->postJson('/api/plans/sync', ['force' => true]);

        $response->assertStatus(200)
            ->assertJsonPath('plans_synced', 0)
            ->assertJsonPath('error_type', 'provider_failure');
    }

    /**
     * Test health check muestra degraded con circuit breaker open
     */
    public function test_health_check_shows_degraded_with_circuit_open(): void
    {
        // Forzar circuit breaker open
        CircuitBreakerService::forceState('provider_api', 'OPEN');

        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('services.external_api', 'unhealthy: circuit breaker open');
    }
}
