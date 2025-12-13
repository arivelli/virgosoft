<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MatchingEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(
        private MatchingEngineService $matchingEngine
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'symbol' => ['required', 'string', 'in:BTC-USD,ETH-USD'],
        ]);

        $orders = DB::table('orders')
            ->where('user_id', $request->user()->id)
            ->where('symbol', $request->get('symbol'))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($order) => [
                'id' => $order->id,
                'symbol' => $order->symbol,
                'side' => $order->side,
                'price' => $order->price,
                'amount' => $order->amount,
                'status' => $order->status,
                'locked_usd' => $order->locked_usd,
                'locked_asset' => $order->locked_asset,
                'created_at' => $order->created_at,
            ]);

        return response()->json($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'symbol' => ['required', 'string', 'in:BTC-USD,ETH-USD'],
            'side' => ['required', 'string', 'in:buy,sell'],
            'price' => ['required', 'numeric', 'decimal:0,18', 'gt:0'],
            'amount' => ['required', 'numeric', 'decimal:0,18', 'gt:0'],
        ]);

        try {
            $order = $this->matchingEngine->createOrder(
                $request->user(),
                $data['symbol'],
                $data['side'],
                $data['price'],
                $data['amount']
            );

            return response()->json([
                'id' => $order->id,
                'symbol' => $order->symbol,
                'side' => $order->side,
                'price' => $order->price,
                'amount' => $order->amount,
                'status' => $order->status,
                'created_at' => $order->created_at,
            ], 201);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'order' => $e->getMessage(),
            ]);
        }
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        try {
            $order = $this->matchingEngine->cancelOrder(
                $request->user(),
                $id
            );

            return response()->json([
                'id' => $order->id,
                'status' => $order->status,
            ]);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'order' => $e->getMessage(),
            ]);
        }
    }
}
