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
        Schema::create('bots', function (Blueprint $table) {
            $table->id();


            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('exchange_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('strategy_id')->constrained()->restrictOnDelete();

            $table->string('name');

            $table->decimal('risk_per_trade', 5, 2)->default(1.00);
            $table->integer('max_open_positions')->default(1);

            $table->string('status')->default('active');

            $table->timestamp('last_run_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bots');
    }
};
