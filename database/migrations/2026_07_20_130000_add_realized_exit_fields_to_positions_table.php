<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->decimal('sold_quantity', 18, 8)->default(0)->after('quantity');
            $table->decimal('realized_pnl', 18, 8)->default(0)->after('half_sold');
            $table->decimal('realized_fees', 18, 8)->default(0)->after('realized_pnl');
            $table->decimal('realized_exit_value', 18, 8)->default(0)->after('realized_fees');
        });
    }

    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropColumn([
                'sold_quantity',
                'realized_pnl',
                'realized_fees',
                'realized_exit_value',
            ]);
        });
    }
};
