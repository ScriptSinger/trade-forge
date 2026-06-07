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
        Schema::create('trades', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();

            $table->string('symbol');

            $table->decimal('entry_price', 18, 8);
            $table->decimal('exit_price', 18, 8);

            $table->decimal('quantity', 18, 8);

            $table->decimal('profit_loss', 18, 8);
            $table->decimal('profit_percent', 8, 2);

            $table->decimal('fees', 18, 8)->default(0);

            $table->timestamp('opened_at');
            $table->timestamp('closed_at');

            $table->timestamps();

            $table->index(['bot_id', 'symbol']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
