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
        Schema::create('exchange_accounts', function (Blueprint $table) {
            $table->id();


            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->string('exchange'); // bybit
            $table->string('name')->nullable();

            $table->text('api_key');
            $table->text('api_secret');

            $table->boolean('testnet')->default(false);

            $table->string('status')->default('active');
            $table->timestamp('last_checked_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_accounts');
    }
};
