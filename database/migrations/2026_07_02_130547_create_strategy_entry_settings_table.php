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
        Schema::create('strategy_entry_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('strategy_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedInteger('interval')->default(1);
            $table->unsignedInteger('period')->default(20);

            $table->unsignedInteger('ema_fast')->default(50);
            $table->unsignedInteger('ema_slow')->default(200);

            $table->decimal('adx_min', 5, 2)->default(20);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('strategy_entry_settings');
    }
};
