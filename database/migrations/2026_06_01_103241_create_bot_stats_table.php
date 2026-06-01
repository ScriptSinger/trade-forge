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
        Schema::create('bot_stats', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bot_id')->constrained()->restrictOnDelete();

            $table->date('date');

            $table->integer('total_trades')->default(0);
            $table->integer('wins')->default(0);
            $table->integer('losses')->default(0);

            $table->decimal('winrate', 5, 2)->default(0);

            $table->decimal('profit', 18, 8)->default(0);
            $table->decimal('fees', 18, 8)->default(0);

            $table->timestamps();

            $table->unique(['bot_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_stats');
    }
};
