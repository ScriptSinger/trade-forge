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

            $table->decimal('entry_price', 18, 8);
            $table->decimal('quantity', 18, 8);

            $table->decimal('sl', 18, 8)->nullable();
            $table->decimal('tp', 18, 8)->nullable();

            $table->boolean('be_activated')->default(false);
            $table->boolean('trailing_active')->default(false);
            $table->boolean('half_sold')->default(false);

            $table->string('status')->default('open');

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
