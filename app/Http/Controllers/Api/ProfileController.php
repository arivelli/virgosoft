<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $assets = \DB::table('assets')
            ->where('user_id', $user->id)
            ->get()
            ->map(fn ($asset) => [
                'symbol' => $asset->symbol,
                'amount' => $asset->amount,
                'locked_amount' => $asset->locked_amount,
            ]);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'balance' => $user->balance,
            'assets' => $assets,
        ]);
    }
}
