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

            $table->unsignedInteger('max_positions')->default(3);
            $table->decimal('max_risk_per_trade', 5, 4)->default(0.02);

            $table->boolean('daily_target_enabled')->default(true);
            $table->decimal('daily_profit_target_pct', 5, 2)->default(2.30);

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
