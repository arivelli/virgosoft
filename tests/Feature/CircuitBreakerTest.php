<?php

namespace Tests\Feature;

use App\Services\CircuitBreakerService;
use App\Services\ProviderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear datos de prueba
        $this->seed(\Database\Seeders\PlanSeeder::class);
    }

    /**
     * Test circuit breaker opens after 3 failures
     */
    public function test_circuit_breaker_opens_after_3_failures(): void
    {
        // Resetear circuit breaker antes del test
        CircuitBreakerService::reset('provider_api');

        // Mock HTTP to always fail - LIMPIAR después del test
        Http::fake([
            'provider.code-challenge.feverup.com/api/events' => Http::response('', 503),
        ]);

        $providerService = app(ProviderService::class);

        // First 3 calls should fail but reach the provider
        for ($i = 1; $i <= 3; $i++) {
            try {
                $providerService->fetchPlans();
                $this->fail("Expected exception on call $i");
            } catch (\Exception $e) {
                // Los primeros 3 fallos son del provider, no del circuit breaker
                $this->assertStringContainsString('Unable to fetch plans from provider', $e->getMessage());
            }
        }

        // 4th call should be blocked by circuit breaker
        try {
            $providerService->fetchPlans();
            $this->fail('Expected circuit breaker to be open');
        } catch (\Exception $e) {
            // El 4to fallo es del circuit breaker abierto
            $this->assertStringContainsString('Service provider_api is temporarily unavailable (circuit breaker open)', $e->getMessage());
        }

        // LIMPIAR HTTP fake para no afectar otros tests
        Http::fake([]);
    }

    /**
     * Test API continues working when circuit breaker is open
     */
    public function test_api_continues_working_when_circuit_open(): void
    {
        // Forzar circuit breaker open NO debería afectar la búsqueda local
        CircuitBreakerService::forceState('provider_api', 'OPEN');

        // La búsqueda debería funcionar igual que en PlanApiTest
        $response = $this->getJson('/api/plans?starts_at=2024-01-01&ends_at=2024-12-31');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 4); // Mismo resultado que PlanApiTest
    }

    /**
     * Test sync fails gracefully when circuit breaker open
     */
    public function test_sync_fails_gracefully_when_circuit_open(): void
    {
        // Force circuit breaker open
        CircuitBreakerService::forceState('provider_api', 'OPEN');

        $response = $this->postJson('/api/plans/sync', ['force' => true]);

        $response->assertStatus(200)
            ->assertJsonPath('plans_synced', 0)
            ->assertJsonPath('error_type', 'provider_failure');
    }

    /**
     * Test health check shows degraded status
     */
    public function test_health_check_shows_degraded_status(): void
    {
        // Force circuit breaker open
        CircuitBreakerService::forceState('provider_api', 'OPEN');

        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('services.external_api', 'unhealthy: circuit breaker open');
    }

    /**
     * Test circuit breaker recovery
     */
    public function test_circuit_breaker_recovers_after_timeout(): void
    {
        // This test would require mocking time or using actual waiting
        // For demonstration purposes, showing the structure
        $this->markTestIncomplete('Requires time mocking for proper testing');

        // 1. Force circuit breaker open
        // 2. Wait for timeout period
        // 3. Verify it transitions to HALF_OPEN
        // 4. Mock successful provider response
        // 5. Verify it closes after success threshold
    }
}
