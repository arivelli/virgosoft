<?php

namespace Tests\Unit;

use App\Events\OrderMatched;
use App\Models\Order;
use App\Models\User;
use App\Services\MatchingEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MatchingEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    private MatchingEngineService $service;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MatchingEngineService::class);

        $this->alice = User::factory()->create([
            'balance' => '100000.000000000000000000',
        ]);

        $this->bob = User::factory()->create([
            'balance' => '100000.000000000000000000',
        ]);

        // Seed assets for both users
        DB::table('assets')->insert([
            ['user_id' => $this->alice->id, 'symbol' => 'BTC', 'amount' => '1.000000000000000000', 'locked_amount' => '0.000000000000000000'],
            ['user_id' => $this->alice->id, 'symbol' => 'ETH', 'amount' => '10.000000000000000000', 'locked_amount' => '0.000000000000000000'],
            ['user_id' => $this->bob->id, 'symbol' => 'BTC', 'amount' => '1.000000000000000000', 'locked_amount' => '0.000000000000000000'],
            ['user_id' => $this->bob->id, 'symbol' => 'ETH', 'amount' => '10.000000000000000000', 'locked_amount' => '0.000000000000000000'],
        ]);
    }

    public function test_create_buy_order_locks_usd_and_creates_order()
    {
        $price = '50000.000000000000000000';
        $amount = '0.010000000000000000';
        $requiredUsd = '500.000000000000000000';

        $order = $this->service->createOrder(
            user: $this->alice,
            symbol: 'BTC-USD',
            side: 'buy',
            price: $price,
            amount: $amount
        );

        $this->assertEquals($this->alice->id, $order->user_id);
        $this->assertEquals('BTC-USD', $order->symbol);
        $this->assertEquals('buy', $order->side);
        $this->assertEquals($price, $order->price);
        $this->assertEquals($amount, $order->amount);
        $this->assertEquals(Order::STATUS_OPEN, $order->status);
        $this->assertEquals($requiredUsd, $order->locked_usd);
        $this->assertEquals('0.000000000000000000', $order->locked_asset);

        // Verify USD was locked
        $alice = $this->alice->fresh();
        $expectedBalance = '99500.000000000000000000';
        $this->assertEquals($expectedBalance, $alice->balance);
    }

    public function test_create_sell_order_locks_asset_and_creates_order()
    {
        $price = '50000.000000000000000000';
        $amount = '0.010000000000000000';

        $order = $this->service->createOrder(
            user: $this->bob,
            symbol: 'BTC-USD',
            side: 'sell',
            price: $price,
            amount: $amount
        );

        $this->assertEquals($this->bob->id, $order->user_id);
        $this->assertEquals('sell', $order->side);
        $this->assertEquals('0.000000000000000000', $order->locked_usd);
        $this->assertEquals($amount, $order->locked_asset);

        // Verify asset was locked
        $bobAsset = DB::table('assets')
            ->where('user_id', $this->bob->id)
            ->where('symbol', 'BTC')
            ->first();

        $this->assertEquals('0.990000000000000000', $bobAsset->amount);
        $this->assertEquals('0.010000000000000000', $bobAsset->locked_amount);
    }

    public function test_create_buy_order_not_enough_balance_throws_exception()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Not enough USD balance');

        $this->service->createOrder(
            user: $this->alice,
            symbol: 'BTC-USD',
            side: 'buy',
            price: '50000.000000000000000000',
            amount: '3.000000000000000000' // Requires 150k USD, only has 100k
        );
    }

    public function test_create_sell_order_not_enough_assets_throws_exception()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Not enough assets');

        $this->service->createOrder(
            user: $this->bob,
            symbol: 'BTC-USD',
            side: 'sell',
            price: '50000.000000000000000000',
            amount: '2.000000000000000000' // Only has 1 BTC
        );
    }

    public function test_matching_exact_amounts_creates_trade_and_dispatches_event()
    {
        Event::fake();

        // Create buy order
        $buyOrder = $this->service->createOrder(
            user: $this->alice,
            symbol: 'BTC-USD',
            side: 'buy',
            price: '50000.000000000000000000',
            amount: '0.010000000000000000'
        );

        // Create matching sell order
        $sellOrder = $this->service->createOrder(
            user: $this->bob,
            symbol: 'BTC-USD',
            side: 'sell',
            price: '50000.000000000000000000',
            amount: '0.010000000000000000'
        );

        // Verify orders are filled
        $this->assertEquals(Order::STATUS_FILLED, DB::table('orders')->find($buyOrder->id)->status);
        $this->assertEquals(Order::STATUS_FILLED, DB::table('orders')->find($sellOrder->id)->status);

        // Verify trade was created
        $trade = DB::table('trades')->first();
        $this->assertNotNull($trade);
        $this->assertEquals('50000.000000000000000000', $trade->price);
        $this->assertEquals('0.010000000000000000', $trade->amount);
        $this->assertEquals('500.000000000000000000', $trade->usd_value);
        $this->assertEquals('7.500000000000000000', $trade->commission_usd); // 1.5% of 500

        // Verify balances
        $alice = $this->alice->fresh();
        $bob = $this->bob->fresh();

        // Alice: 100000 - 500 (locked) = 99500
        $this->assertEquals('99500.000000000000000000', $alice->balance);

        // Bob: 100000 + 492.5 (500 - 7.5 commission) = 100492.5
        $this->assertEquals('100492.500000000000000000', $bob->balance);

        // Verify assets
        $aliceAsset = DB::table('assets')
            ->where('user_id', $this->alice->id)
            ->where('symbol', 'BTC')
            ->first();
        $bobAsset = DB::table('assets')
            ->where('user_id', $this->bob->id)
            ->where('symbol', 'BTC')
            ->first();

        // Alice gained BTC
        $this->assertEquals('1.010000000000000000', $aliceAsset->amount);
        $this->assertEquals('0.000000000000000000', $aliceAsset->locked_amount);

        // Bob's locked asset was released
        $this->assertEquals('0.990000000000000000', $bobAsset->amount);
        $this->assertEquals('0.000000000000000000', $bobAsset->locked_amount);

        // Verify event was dispatched
        Event::assertDispatched(OrderMatched::class, function ($event) use ($buyOrder, $sellOrder) {
            return $event->buyOrderId === $buyOrder->id
                && $event->sellOrderId === $sellOrder->id
                && $event->buyUserId === $this->alice->id
                && $event->sellUserId === $this->bob->id;
        });
    }

    public function test_non_matching_amounts_do_not_trade()
    {
        // Create buy order for 0.01 BTC
        $buyOrder = $this->service->createOrder(
            user: $this->alice,
            symbol: 'BTC-USD',
            side: 'buy',
            price: '50000.000000000000000000',
            amount: '0.010000000000000000'
        );

        // Create sell order for 0.02 BTC (different amount)
        $sellOrder = $this->service->createOrder(
            user: $this->bob,
            symbol: 'BTC-USD',
            side: 'sell',
            price: '50000.000000000000000000',
            amount: '0.020000000000000000'
        );

        // Verify orders remain open
        $this->assertEquals(Order::STATUS_OPEN, DB::table('orders')->find($buyOrder->id)->status);
        $this->assertEquals(Order::STATUS_OPEN, DB::table('orders')->find($sellOrder->id)->status);

        // Verify no trade was created
        $this->assertEmpty(DB::table('trades')->get());
    }

    public function test_price_priority_matching()
    {
        // Create buy order at 50000
        $buyOrder = $this->service->createOrder(
            user: $this->alice,
            symbol: 'BTC-USD',
            side: 'buy',
            price: '50000.000000000000000000',
            amount: '0.010000000000000000'
        );

        // Create sell order at 49000 (should match with buy at 50000)
        $sellOrder = $this->service->createOrder(
            user: $this->bob,
            symbol: 'BTC-USD',
            side: 'sell',
            price: '49000.000000000000000000',
            amount: '0.010000000000000000'
        );

        // Verify orders are filled
        $this->assertEquals(Order::STATUS_FILLED, DB::table('orders')->find($buyOrder->id)->status);
        $this->assertEquals(Order::STATUS_FILLED, DB::table('orders')->find($sellOrder->id)->status);

        // Verify trade was created at buy order price (50000)
        $trade = DB::table('trades')->first();
        $this->assertEquals('50000.000000000000000000', $trade->price);
    }

    public function test_cancel_buy_order_restores_usd_balance()
    {
        $order = $this->service->createOrder(
            user: $this->alice,
            symbol: 'BTC-USD',
            side: 'buy',
            price: '50000.000000000000000000',
            amount: '0.010000000000000000'
        );

        $canceledOrder = $this->service->cancelOrder(
            user: $this->alice,
            id: $order->id
        );

        $this->assertEquals(Order::STATUS_CANCELLED, $canceledOrder->status);

        // Verify balance was restored
        $alice = $this->alice->fresh();
        $this->assertEquals('100000.000000000000000000', $alice->balance);
    }

    public function test_cancel_sell_order_restores_asset_balance()
    {
        $order = $this->service->createOrder(
            user: $this->bob,
            symbol: 'BTC-USD',
            side: 'sell',
            price: '50000.000000000000000000',
            amount: '0.010000000000000000'
        );

        $canceledOrder = $this->service->cancelOrder(
            user: $this->bob,
            id: $order->id
        );

        $this->assertEquals(Order::STATUS_CANCELLED, $canceledOrder->status);

        // Verify asset was restored
        $bobAsset = DB::table('assets')
            ->where('user_id', $this->bob->id)
            ->where('symbol', 'BTC')
            ->first();

        $this->assertEquals('1.000000000000000000', $bobAsset->amount);
        $this->assertEquals('0.000000000000000000', $bobAsset->locked_amount);
    }

    public function test_cancel_filled_order_throws_exception()
    {
        // Create matching orders that will fill
        $buyOrder = $this->service->createOrder(
            user: $this->alice,
            symbol: 'BTC-USD',
            side: 'buy',
            price: '50000.000000000000000000',
            amount: '0.010000000000000000'
        );

        $this->service->createOrder(
            user: $this->bob,
            symbol: 'BTC-USD',
            side: 'sell',
            price: '50000.000000000000000000',
            amount: '0.010000000000000000'
        );

        // Try to cancel the filled order
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Can't cancel this order");

        $this->service->cancelOrder(
            user: $this->alice,
            id: $buyOrder->id
        );
    }

    public function test_cancel_other_users_order_throws_exception()
    {
        $order = $this->service->createOrder(
            user: $this->alice,
            symbol: 'BTC-USD',
            side: 'buy',
            price: '50000.000000000000000000',
            amount: '0.010000000000000000'
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Can't cancel this order");

        $this->service->cancelOrder(
            user: $this->bob,
            id: $order->id
        );
    }
}
