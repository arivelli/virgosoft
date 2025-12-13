<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/health');

        $response->assertOk();
    }
}
