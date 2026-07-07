<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exchange_account_id')->constrained()->cascadeOnDelete();

            $table->string('symbol');

            $table->string('side'); // buy, sell
            $table->string('type'); // market, limit

            $table->decimal('price', 18, 8)->nullable();
            $table->decimal('quantity', 18, 8);

            $table->string('status')->default('new');

            $table->string('exchange_order_id')->nullable();

            $table->json('raw_response')->nullable();

            $table->index(['bot_id', 'symbol']);
            $table->timestamps();
        });

        Schema::table('bot_runs', function (Blueprint $table) {
            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bot_runs', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::dropIfExists('orders');
    }
};
