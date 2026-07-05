<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_runs', function (Blueprint $table) {
            if (!Schema::hasColumn('bot_runs', 'quantity')) {
                $table->decimal('quantity', 18, 8)->nullable()->after('reason');
            }

            if (!Schema::hasColumn('bot_runs', 'mode')) {
                $table->string('mode')->nullable()->after('quantity');
            }

            if (!Schema::hasColumn('bot_runs', 'stop_loss')) {
                $table->decimal('stop_loss', 18, 8)->nullable()->after('mode');
            }

            if (!Schema::hasColumn('bot_runs', 'take_profit')) {
                $table->decimal('take_profit', 18, 8)->nullable()->after('stop_loss');
            }

            if (!Schema::hasColumn('bot_runs', 'order_id')) {
                $table->unsignedBigInteger('order_id')->nullable()->after('take_profit');
            }
        });

        $this->ensureOrderForeignKey();
    }

    public function down(): void
    {
        $this->dropOrderForeignKeyIfExists();

        Schema::table('bot_runs', function (Blueprint $table) {
            $columns = ['take_profit', 'stop_loss', 'mode', 'quantity'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('bot_runs', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('bot_runs', 'order_id')) {
                $table->dropColumn('order_id');
            }
        });
    }

    private function ensureOrderForeignKey(): void
    {
        if (!Schema::hasTable('orders') || !Schema::hasColumn('bot_runs', 'order_id')) {
            return;
        }

        if ($this->orderForeignKeyExists()) {
            return;
        }

        Schema::table('bot_runs', function (Blueprint $table) {
            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->nullOnDelete();
        });
    }

    private function dropOrderForeignKeyIfExists(): void
    {
        if (!$this->orderForeignKeyExists()) {
            return;
        }

        Schema::table('bot_runs', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });
    }

    private function orderForeignKeyExists(): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $keys = DB::select("PRAGMA foreign_key_list('bot_runs')");

            foreach ($keys as $key) {
                if (($key->from ?? null) === 'order_id') {
                    return true;
                }
            }

            return false;
        }

        if ($driver !== 'mysql') {
            return false;
        }

        $database = Schema::getConnection()->getDatabaseName();

        $result = DB::selectOne(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND CONSTRAINT_TYPE = ?
               AND CONSTRAINT_NAME = ?',
            [$database, 'bot_runs', 'FOREIGN KEY', 'bot_runs_order_id_foreign'],
        );

        return $result !== null;
    }
};