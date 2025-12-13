<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\OrderController;
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
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'message' => 'Base API is running',
            'timestamp' => now()->toISOString(),
        ]);
    });

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    Route::get('/profile', ProfileController::class);
    
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
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
        ],
    ], 404);
});
