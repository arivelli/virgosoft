<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('symbol', 16);
            $table->string('side', 8);
            $table->decimal('price', 36, 18);
            $table->decimal('amount', 36, 18);
            $table->unsignedTinyInteger('status');
            $table->decimal('locked_usd', 36, 18)->default(0);
            $table->decimal('locked_asset', 36, 18)->default(0);
            $table->timestamps();

            $table->index(['symbol', 'side', 'status', 'price', 'created_at', 'id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
