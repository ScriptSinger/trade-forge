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
        Schema::create('bot_runs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();

            $table->string('symbol');
            $table->decimal('market_price', 18, 8)->nullable();

            $table->string('signal')->nullable(); // buy, sell, hold

            $table->json('indicators')->nullable(); // EMA, RSI, ADX
            $table->text('reason')->nullable();

            $table->string('status')->default('success');



            $table->index(['bot_id', 'symbol']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_runs');
    }
};
