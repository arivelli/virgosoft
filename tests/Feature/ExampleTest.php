<?php

namespace Tests\Feature;

use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use App\Models\User;

class ExampleTest extends TestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());
        
        $response = $this->getJson('/api/health');

        $response->assertOk();
    }
}
