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
        Schema::create('strategy_btc_trend_filters', function (Blueprint $table) {
            $table->id();

            $table->foreignId('strategy_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->boolean('enabled')->default(true);

            $table->string('benchmark_symbol')->default('BTCUSDT');
            $table->unsignedInteger('benchmark_interval')->default(60);

            $table->unsignedInteger('ema_fast')->default(50);
            $table->unsignedInteger('ema_slow')->default(200);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('strategy_btc_trend_filters');
    }
};
