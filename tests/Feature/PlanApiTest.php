<?php

namespace Tests\Feature;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear datos de prueba
        $this->seed(\Database\Seeders\PlanSeeder::class);
    }

    /**
     * Test health check endpoint
     */
    public function test_health_check_returns_success(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'timestamp',
                'services' => [
                    'database',
                    'redis',
                    'external_api',
                ],
                'version',
            ]);
    }

    public function test_plan_search_with_valid_dates(): void
    {
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
        // Jazz Night es offline y no aparece con el filtro por defecto "online"
        $response->assertJsonPath('meta.total', 2);
        $response->assertJsonPath('data.0.title', 'Taylor Swift Concert');
        $response->assertJsonPath('data.1.title', 'Food & Wine Festival');
    }

    /**
     * Test plan search filters only online plans
     */
    public function test_plan_search_filters_online_plans_only(): void
    {
        $response = $this->getJson('/api/plans?starts_at=2024-04-01&ends_at=2024-04-30');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 0); // Jazz Night es offline, no debería aparecer
    }

    /**
     * Test plan search with invalid date range
     */
    public function test_plan_search_with_invalid_date_range(): void
    {
        $response = $this->getJson('/api/plans?starts_at=2024-01-01&ends_at=2027-01-01');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ends_at']);
    }

    /**
     * Test plan search with missing parameters
     */
    public function test_plan_search_with_missing_parameters(): void
    {
        $response = $this->getJson('/api/plans');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['starts_at', 'ends_at']);
    }

    /**
     * Test plan search with pagination
     */
    public function test_plan_search_with_pagination(): void
    {
        $response = $this->getJson('/api/plans?starts_at=2024-01-01&ends_at=2024-12-31&limit=2&offset=0');

        $response->assertStatus(200)
            ->assertJsonPath('meta.limit', 2) // Viene como integer
            ->assertJsonPath('meta.offset', 0)   // Viene como integer
            ->assertJsonPath('meta.has_more', true); // Hay más de 2 planes online
    }

    /**
     * Test plan search performance headers
     */
    public function test_plan_search_includes_performance_headers(): void
    {
        $response = $this->getJson('/api/plans?starts_at=2024-01-01&ends_at=2024-06-30');

        $response->assertStatus(200)
            ->assertHeader('X-Response-Time')
            ->assertHeader('X-Results-Count')
            ->assertHeader('X-Total-Count');
    }

    /**
     * Test sync endpoint
     */
    public function test_sync_endpoint(): void
    {
        // Mock the ProviderService to avoid external HTTP calls
        $this->mock(\App\Services\ProviderService::class, function ($mock) {
            $mock->shouldReceive('fetchPlans')
                ->once()
                ->andReturn([]);
        });

        $response = $this->postJson('/api/plans/sync', ['force' => true]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'plans_synced',
                'async',
                'duration_ms',
                'stats',
            ])
            ->assertJson([
                'message' => 'No plans found from provider',
                'plans_synced' => 0,
                'async' => false,
            ]);
    }

    /**
     * Test plan search with sell_mode filter
     */
    public function test_plan_search_with_sell_mode_filter(): void
    {
        $response = $this->getJson('/api/plans?starts_at=2024-01-01&ends_at=2024-12-31&sell_mode=offline');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1) // Solo Jazz Night es offline
            ->assertJsonPath('data.0.title', 'Jazz Night');
    }

    /**
     * Test plan search with complex date range scenarios
     */
    public function test_plan_search_complex_date_scenarios(): void
    {
        // Test plan that starts before range but ends within range
        $response = $this->getJson('/api/plans?starts_at=2024-03-16&ends_at=2024-03-17');
        $response->assertJsonPath('meta.total', 0);

        // Test plan that starts within range but ends after range
        $response = $this->getJson('/api/plans?starts_at=2024-03-14&ends_at=2024-03-16');
        $response->assertJsonPath('meta.total', 1);

        // Test plan that completely covers the range (usando hora completa para incluir todo el día)
        $response = $this->getJson('/api/plans?starts_at=2024-05-21T00:00:00&ends_at=2024-05-21T23:59:59');
        $response->assertJsonPath('meta.total', 1);
    }
}
