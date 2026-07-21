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
        Schema::create('positions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();

            $table->string('symbol');
            $table->string('mode', 16)->default('Sniper');

            $table->decimal('entry_price', 18, 8);
            $table->decimal('current_price', 18, 8)->nullable();
            $table->decimal('pnl_pct', 10, 2)->default(0);
            $table->decimal('quantity', 18, 8);
            // Hybrid partial exits: remainder qty tracking + realized legs for combined Trade PnL
            $table->decimal('sold_quantity', 18, 8)->default(0);
            $table->decimal('realized_pnl', 18, 8)->default(0);
            $table->decimal('realized_fees', 18, 8)->default(0);
            $table->decimal('realized_exit_value', 18, 8)->default(0);

            $table->decimal('sl', 18, 8)->nullable();
            $table->decimal('tp', 18, 8)->nullable();

            $table->boolean('be_activated')->default(false);
            $table->boolean('trailing_active')->default(false);
            $table->boolean('half_sold')->default(false);

            $table->string('status')->default('open');
            $table->string('exit_reason')->nullable();

            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            $table->index(['bot_id', 'symbol']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
