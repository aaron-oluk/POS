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
        // Was an enum (CHECK-constraint-backed varchar under Postgres/SQLite).
        // Changing an enum's allowed values via ->change() emits invalid
        // "ALTER COLUMN TYPE ... CHECK (...)" SQL on Postgres — a Laravel/PG
        // grammar limitation, not fixable by tweaking the enum() call. A
        // plain string sidesteps it entirely; the allowed-values list is
        // already enforced at the application layer (validation rules).
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->string('reason')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->enum('reason', ['waste', 'damage', 'theft', 'recount', 'other'])->change();
        });
    }
};
