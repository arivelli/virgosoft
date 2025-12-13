<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Events\OrderMatched;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class MatchingEngineService
{
    public function createOrder(User $user, string $symbol, string $side, string $price, string $amount): object
    {
        return DB::transaction(function () use ($user, $symbol, $side, $price, $amount) {
            $assetSymbol = str_replace('-USD', '', $symbol);
            
            if ($side === 'buy') {
                $requiredUsd = \bcmul($price, $amount, 18);
                
                $user = DB::table('users')
                    ->where('id', $user->id)
                    ->lockForUpdate()
                    ->first();
                
                if (\bccomp($user->balance, $requiredUsd, 18) < 0) {
                    throw new \Exception('Not enough USD balance');
                }
                
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['balance' => \bcsub($user->balance, $requiredUsd, 18)]);
                
                $lockedUsd = $requiredUsd;
                $lockedAsset = '0';
            } else {
                $userAsset = DB::table('assets')
                    ->where('user_id', $user->id)
                    ->where('symbol', $assetSymbol)
                    ->lockForUpdate()
                    ->first();
                
                if (!$userAsset) {
                    throw new \Exception('Asset not found');
                }
                
                if (\bccomp($userAsset->amount, $amount, 18) < 0) {
                    throw new \Exception('Not enough assets');
                }
                
                DB::table('assets')
                    ->where('id', $userAsset->id)
                    ->update([
                        'amount' => \bcsub($userAsset->amount, $amount, 18),
                        'locked_amount' => \bcadd($userAsset->locked_amount, $amount, 18),
                    ]);
                
                $lockedUsd = '0';
                $lockedAsset = $amount;
            }
            
            $order = DB::table('orders')->insertGetId([
                'user_id' => $user->id,
                'symbol' => $symbol,
                'side' => $side,
                'price' => $price,
                'amount' => $amount,
                'status' => Order::STATUS_OPEN,
                'locked_usd' => $lockedUsd,
                'locked_asset' => $lockedAsset,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $order = DB::table('orders')->where('id', $order)->first();
            
            $trade = $this->matchOrder($order);
            
            if ($trade) {
                DB::afterCommit(function () use ($trade) {
                    Event::dispatch(new OrderMatched(
                        buyOrderId: $trade['buy_order_id'],
                        sellOrderId: $trade['sell_order_id'],
                        buyUserId: $trade['buy_user_id'],
                        sellUserId: $trade['sell_user_id'],
                        symbol: $trade['symbol'],
                        price: $trade['price'],
                        amount: $trade['amount'],
                        usdValue: $trade['usd_value'],
                        commissionUsd: $trade['commission_usd']
                    ));
                });
            }
            
            return $order;
        });
    }
    
    public function cancelOrder(User $user, int $id): object
    {
        return DB::transaction(function () use ($user, $id) {
            $order = DB::table('orders')
                ->where('id', $id)
                ->where('user_id', $user->id)
                ->where('status', Order::STATUS_OPEN)
                ->lockForUpdate()
                ->first();
            
            if (!$order) {
                throw new \Exception("Can't cancel this order");
            }
            
            if (\bccomp($order->locked_usd, '0', 18) > 0) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->increment('balance', $order->locked_usd);
            }
            
            if (\bccomp($order->locked_asset, '0', 18) > 0) {
                $assetSymbol = str_replace('-USD', '', $order->symbol);
                DB::table('assets')
                    ->where('user_id', $user->id)
                    ->where('symbol', $assetSymbol)
                    ->update([
                        'locked_amount' => DB::raw("locked_amount - {$order->locked_asset}"),
                        'amount' => DB::raw("amount + {$order->locked_asset}"),
                    ]);
            }
            
            DB::table('orders')
                ->where('id', $id)
                ->update(['status' => Order::STATUS_CANCELLED]);
            
            $order->status = Order::STATUS_CANCELLED;
            
            return $order;
        });
    }
    
    private function matchOrder($order): ?array
    {
        $oppositeSide = $order->side === 'buy' ? 'sell' : 'buy';
        $comparison = $order->side === 'buy' ? '<=' : '>=';
        
        $matchingOrders = DB::table('orders')
            ->where('symbol', $order->symbol)
            ->where('side', $oppositeSide)
            ->where('status', Order::STATUS_OPEN)
            ->where('price', $comparison, $order->price)
            ->orderBy('price', $order->side === 'buy' ? 'asc' : 'desc')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get();
        
        foreach ($matchingOrders as $matchOrder) {
            if (\bccomp($matchOrder->amount, $order->amount, 18) !== 0) {
                continue;
            }
            
            return $this->executeMatch($order, $matchOrder);
        }
        
        return null;
    }
    
    private function executeMatch($order1, $order2): array
    {
        // Determine which is buy and which is sell
        $buyOrder = $order1->side === 'buy' ? $order1 : $order2;
        $sellOrder = $order1->side === 'sell' ? $order1 : $order2;
        
        $price = $buyOrder->price;
        $amount = $buyOrder->amount;
        $usdValue = \bcmul($price, $amount, 18);
        $commissionUsd = \bcmul($usdValue, '0.015', 18);
        $netUsdValue = \bcsub($usdValue, $commissionUsd, 18);
        
        $assetSymbol = str_replace('-USD', '', $buyOrder->symbol);
        
        DB::table('orders')
            ->where('id', $buyOrder->id)
            ->update(['status' => Order::STATUS_FILLED]);
        
        DB::table('orders')
            ->where('id', $sellOrder->id)
            ->update(['status' => Order::STATUS_FILLED]);
        
        DB::table('assets')
            ->where('user_id', $buyOrder->user_id)
            ->where('symbol', $assetSymbol)
            ->increment('amount', $amount);
        
        DB::table('assets')
            ->where('user_id', $sellOrder->user_id)
            ->where('symbol', $assetSymbol)
            ->update([
                'locked_amount' => DB::raw("locked_amount - {$amount}"),
            ]);
        
        DB::table('users')
            ->where('id', $sellOrder->user_id)
            ->increment('balance', $netUsdValue);
        
        DB::table('trades')->insert([
            'symbol' => $buyOrder->symbol,
            'buy_order_id' => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
            'price' => $price,
            'amount' => $amount,
            'usd_value' => $usdValue,
            'commission_usd' => $commissionUsd,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        return [
            'buy_order_id' => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
            'buy_user_id' => $buyOrder->user_id,
            'sell_user_id' => $sellOrder->user_id,
            'symbol' => $buyOrder->symbol,
            'price' => $price,
            'amount' => $amount,
            'usd_value' => $usdValue,
            'commission_usd' => $commissionUsd,
        ];
    }
}
