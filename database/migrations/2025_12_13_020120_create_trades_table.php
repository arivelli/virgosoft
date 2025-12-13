<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 16);
            $table->foreignId('buy_order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('sell_order_id')->constrained('orders')->cascadeOnDelete();
            $table->decimal('price', 36, 18);
            $table->decimal('amount', 36, 18);
            $table->decimal('usd_value', 36, 18);
            $table->decimal('commission_usd', 36, 18);
            $table->timestamps();

            $table->unique('buy_order_id');
            $table->unique('sell_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
