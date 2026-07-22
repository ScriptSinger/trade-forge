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
        Schema::create('strategy_risk_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('strategy_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('sl_multiplier', 5, 2)->default(2.0);
            $table->decimal('tp_multiplier', 5, 2)->default(3.0);
            $table->decimal('trailing_pct', 5, 2)->default(1.5);

            // Hybrid exit (sample aza_trade): portion=0.5, SL → entry * 1.0025 after half TP
            $table->decimal('hybrid_tp_portion', 5, 4)->default(0.5);
            $table->decimal('hybrid_be_multiplier', 8, 6)->default(1.0025);

            $table->unsignedInteger('max_positions')->default(3);
            $table->decimal('max_risk_per_trade', 5, 4)->default(0.02);

            $table->boolean('daily_target_enabled')->default(true);
            $table->decimal('daily_profit_target_pct', 5, 2)->default(2.30);

            $table->decimal('spot_fee_rate', 8, 6)->default(0.001);
            $table->decimal('min_order_usdt', 8, 2)->default(5);
            $table->decimal('max_balance_pct', 5, 4)->default(0.30);
            $table->decimal('free_balance_buffer', 5, 4)->default(0.98);
            $table->unsignedInteger('scanner_cache_ttl')->default(7200);
            $table->json('scanner_excluded_patterns')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('strategy_risk_settings');
    }
};
