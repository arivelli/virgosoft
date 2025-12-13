<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderMatched implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $buyOrderId,
        public int $sellOrderId,
        public int $buyUserId,
        public int $sellUserId,
        public string $symbol,
        public string $price,
        public string $amount,
        public string $usdValue,
        public string $commissionUsd
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('private-user.'.$this->buyUserId),
            new PrivateChannel('private-user.'.$this->sellUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.matched';
    }

    public function broadcastWith(): array
    {
        return [
            'buy_order_id' => $this->buyOrderId,
            'sell_order_id' => $this->sellOrderId,
            'symbol' => $this->symbol,
            'price' => $this->price,
            'amount' => $this->amount,
            'usd_value' => $this->usdValue,
            'commission_usd' => $this->commissionUsd,
        ];
    }
}
