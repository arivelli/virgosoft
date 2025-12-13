<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $users = [
            ['name' => 'Alice', 'email' => 'alice@example.com', 'password' => bcrypt('password'), 'balance' => '100000.000000000000000000'],
            ['name' => 'Bob', 'email' => 'bob@example.com', 'password' => bcrypt('password'), 'balance' => '100000.000000000000000000'],
        ];

        foreach ($users as $userData) {
            $user = User::query()->updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => $userData['password'],
                    'balance' => $userData['balance'],
                ]
            );

            $this->seedAsset($user->id, 'BTC', '1.000000000000000000');
            $this->seedAsset($user->id, 'ETH', '10.000000000000000000');
        }
    }

    private function seedAsset(int $userId, string $symbol, string $amount): void
    {
        DB::table('assets')->updateOrInsert(
            ['user_id' => $userId, 'symbol' => $symbol],
            [
                'amount' => $amount,
                'locked_amount' => '0.000000000000000000',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
