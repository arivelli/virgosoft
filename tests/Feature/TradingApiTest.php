<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TradingApiTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'balance' => '100000.000000000000000000',
        ]);

        $this->bob = User::factory()->create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'balance' => '100000.000000000000000000',
        ]);

        // Seed assets
        \DB::table('assets')->insert([
            ['user_id' => $this->alice->id, 'symbol' => 'BTC', 'amount' => '1.000000000000000000', 'locked_amount' => '0.000000000000000000'],
            ['user_id' => $this->alice->id, 'symbol' => 'ETH', 'amount' => '10.000000000000000000', 'locked_amount' => '0.000000000000000000'],
            ['user_id' => $this->bob->id, 'symbol' => 'BTC', 'amount' => '1.000000000000000000', 'locked_amount' => '0.000000000000000000'],
            ['user_id' => $this->bob->id, 'symbol' => 'ETH', 'amount' => '10.000000000000000000', 'locked_amount' => '0.000000000000000000'],
        ]);
    }

    public function test_login_returns_token()
    {
        $response = $this->postJson('/api/login', [
            'email' => 'alice@example.com',
            'password' => 'password',
            'device_name' => 'test',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token']);
    }

    public function test_profile_returns_user_with_assets()
    {
        Sanctum::actingAs($this->alice);

        $response = $this->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'balance',
                'assets' => [
                    '*' => [
                        'symbol',
                        'amount',
                        'locked_amount',
                    ],
                ],
            ])
            ->assertJson([
                'id' => $this->alice->id,
                'name' => 'Alice',
                'email' => 'alice@example.com',
                'balance' => '100000.000000000000000000',
            ]);
    }

    public function test_profile_requires_authentication()
    {
        $response = $this->getJson('/api/profile');

        $response->assertStatus(401);
    }

    public function test_create_buy_order()
    {
        Sanctum::actingAs($this->alice);

        $response = $this->postJson('/api/orders', [
            'symbol' => 'BTC-USD',
            'side' => 'buy',
            'price' => '50000.00',
            'amount' => '0.01',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'symbol',
                'side',
                'price',
                'amount',
                'status',
                'created_at',
            ])
            ->assertJson([
                'symbol' => 'BTC-USD',
                'side' => 'buy',
                'price' => '50000.000000000000000000',
                'amount' => '0.010000000000000000',
                'status' => Order::STATUS_OPEN,
            ]);

        // Verify USD was locked
        $alice = $this->alice->fresh();
        $this->assertEquals('99500.000000000000000000', $alice->balance);
    }

    public function test_create_sell_order()
    {
        Sanctum::actingAs($this->bob);

        $response = $this->postJson('/api/orders', [
            'symbol' => 'BTC-USD',
            'side' => 'sell',
            'price' => '50000.00',
            'amount' => '0.01',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'symbol' => 'BTC-USD',
                'side' => 'sell',
                'status' => Order::STATUS_OPEN,
            ]);

        // Verify asset was locked
        $bobAsset = \DB::table('assets')
            ->where('user_id', $this->bob->id)
            ->where('symbol', 'BTC')
            ->first();

        $this->assertEquals('0.990000000000000000', $bobAsset->amount);
        $this->assertEquals('0.010000000000000000', $bobAsset->locked_amount);
    }

    public function test_create_order_validation()
    {
        Sanctum::actingAs($this->alice);

        // Test invalid symbol
        $response = $this->postJson('/api/orders', [
            'symbol' => 'INVALID',
            'side' => 'buy',
            'price' => '50000.00',
            'amount' => '0.01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['symbol']);

        // Test invalid side
        $response = $this->postJson('/api/orders', [
            'symbol' => 'BTC-USD',
            'side' => 'invalid',
            'price' => '50000.00',
            'amount' => '0.01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['side']);

        // Test negative price
        $response = $this->postJson('/api/orders', [
            'symbol' => 'BTC-USD',
            'side' => 'buy',
            'price' => '-100',
            'amount' => '0.01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['price']);

        // Test zero amount
        $response = $this->postJson('/api/orders', [
            'symbol' => 'BTC-USD',
            'side' => 'buy',
            'price' => '50000.00',
            'amount' => '0',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_create_order_not_enough_balance()
    {
        Sanctum::actingAs($this->alice);

        $response = $this->postJson('/api/orders', [
            'symbol' => 'BTC-USD',
            'side' => 'buy',
            'price' => '50000.00',
            'amount' => '3', // Requires 150k USD
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order']);
    }

    public function test_list_orders_by_symbol()
    {
        Sanctum::actingAs($this->alice);

        // Create orders
        $this->postJson('/api/orders', [
            'symbol' => 'BTC-USD',
            'side' => 'buy',
            'price' => '50000.00',
            'amount' => '0.01',
        ]);

        $this->postJson('/api/orders', [
            'symbol' => 'ETH-USD',
            'side' => 'buy',
            'price' => '3000.00',
            'amount' => '0.1',
        ]);

        // List BTC orders (orderbook - returns all open orders)
        $response = $this->getJson('/api/orders?symbol=BTC-USD');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.symbol', 'BTC-USD');

        // List ETH orders (orderbook - returns all open orders)
        $response = $this->getJson('/api/orders?symbol=ETH-USD');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.symbol', 'ETH-USD');
    }

    public function test_list_orders_requires_authentication()
    {
        $response = $this->getJson('/api/orders?symbol=BTC-USD');

        $response->assertStatus(401);
    }

    public function test_list_orders_requires_symbol()
    {
        Sanctum::actingAs($this->alice);

        $response = $this->getJson('/api/orders');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['symbol']);
    }

    public function test_cancel_order()
    {
        Sanctum::actingAs($this->alice);

        // Create order
        $createResponse = $this->postJson('/api/orders', [
            'symbol' => 'BTC-USD',
            'side' => 'buy',
            'price' => '50000.00',
            'amount' => '0.01',
        ]);

        $orderId = $createResponse->json('id');

        // Cancel order
        $response = $this->postJson("/api/orders/{$orderId}/cancel");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $orderId,
                'status' => Order::STATUS_CANCELLED,
            ]);

        // Verify balance was restored
        $alice = $this->alice->fresh();
        $this->assertEquals('100000.000000000000000000', $alice->balance);
    }

    public function test_cancel_order_requires_authentication()
    {
        $response = $this->postJson('/api/orders/1/cancel');

        $response->assertStatus(401);
    }

    public function test_cancel_other_users_order()
    {
        Sanctum::actingAs($this->alice);

        // Create order as Alice
        $createResponse = $this->postJson('/api/orders', [
            'symbol' => 'BTC-USD',
            'side' => 'buy',
            'price' => '50000.00',
            'amount' => '0.01',
        ]);

        $orderId = $createResponse->json('id');

        // Try to cancel as Bob
        Sanctum::actingAs($this->bob);
        $response = $this->postJson("/api/orders/{$orderId}/cancel");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order']);
    }

    public function test_cancel_nonexistent_order()
    {
        Sanctum::actingAs($this->alice);

        $response = $this->postJson('/api/orders/999/cancel');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order']);
    }

    public function test_full_order_matching_flow()
    {
        // Alice creates buy order
        Sanctum::actingAs($this->alice);
        $buyResponse = $this->postJson('/api/orders', [
            'symbol' => 'BTC-USD',
            'side' => 'buy',
            'price' => '50000.00',
            'amount' => '0.01',
        ]);

        $buyOrder = $buyResponse->json();

        // Bob creates matching sell order
        Sanctum::actingAs($this->bob);
        $sellResponse = $this->postJson('/api/orders', [
            'symbol' => 'BTC-USD',
            'side' => 'sell',
            'price' => '50000.00',
            'amount' => '0.01',
        ]);

        $sellOrder = $sellResponse->json();

        // Fetch fresh order data from database
        $freshBuyOrder = DB::table('orders')->find($buyOrder['id']);
        $freshSellOrder = DB::table('orders')->find($sellOrder['id']);

        // Both orders should be filled
        $this->assertEquals(Order::STATUS_FILLED, $freshBuyOrder->status);
        $this->assertEquals(Order::STATUS_FILLED, $freshSellOrder->status);

        // Verify trade was created
        $this->assertDatabaseHas('trades', [
            'buy_order_id' => $buyOrder['id'],
            'sell_order_id' => $sellOrder['id'],
            'price' => '50000.000000000000000000',
            'amount' => '0.010000000000000000',
            'usd_value' => '500.000000000000000000',
            'commission_usd' => '7.500000000000000000',
        ]);

        // Verify final balances
        $alice = $this->alice->fresh();
        $bob = $this->bob->fresh();

        $this->assertEquals('99500.000000000000000000', $alice->balance); // 100k - 500
        $this->assertEquals('100492.500000000000000000', $bob->balance);  // 100k + 492.5 (500 - 7.5)
    }

    public function test_logout()
    {
        Sanctum::actingAs($this->alice);

        $response = $this->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }

    public function test_me_endpoint()
    {
        Sanctum::actingAs($this->alice);

        $response = $this->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJson([
                'id' => $this->alice->id,
                'name' => 'Alice',
                'email' => 'alice@example.com',
            ]);
    }
}
