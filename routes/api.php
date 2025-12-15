<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/health', function () {
            return response()->json([
                'status' => 'ok',
                'message' => 'Base API is running',
                'timestamp' => now()->toISOString(),
            ]);
        });
    });

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('throttle:30,1')->group(function () {
        Route::get('/profile', ProfileController::class);

        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    });
    
    // Broadcasting authentication
    Route::post('/broadcasting/auth', function () {
        $pusher = new \Pusher\Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            config('broadcasting.connections.pusher.options')
        );
        
        $channelName = request('channel_name');
        $socketId = request('socket_id');
        
        // For private channels, check if user is authenticated
        if (str_starts_with($channelName, 'private-')) {
            $user = request()->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
            
            // Check if user can access this channel (private-user.{id})
            if (preg_match('/private-user\.(\d+)/', $channelName, $matches)) {
                $userId = (int) $matches[1];
                if ($userId !== $user->id) {
                    return response()->json(['message' => 'Forbidden'], 403);
                }
            }
        }
        
        return $pusher->socket_auth($channelName, $socketId);
    });
});

Route::fallback(function () {
    return response()->json([
        'error' => 'Not found',
        'message' => 'This endpoint does not exist.',
        'available_endpoints' => [
            'POST /api/login' => 'Get API token',
            'GET /api/health' => 'Health check (auth required)',
            'GET /api/me' => 'Current user (auth required)',
            'POST /api/logout' => 'Logout (auth required)',
            'GET /api/profile' => 'User profile and assets (auth required)',
            'GET /api/orders?symbol=BTC-USD' => 'User orders (auth required)',
            'POST /api/orders' => 'Create order (auth required)',
            'POST /api/orders/{id}/cancel' => 'Cancel order (auth required)',
            'POST /api/broadcasting/auth' => 'Pusher authentication (auth required)',
        ],
    ], 404);
});
