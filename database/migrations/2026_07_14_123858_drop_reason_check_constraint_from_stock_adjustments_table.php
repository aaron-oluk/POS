<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The previous migration changed `reason` from enum to string via
        // ->change(), intending to drop the old CHECK constraint that only
        // allowed the original 5 values. On SQLite that works, because
        // ->change() rebuilds the whole table. On Postgres, ALTER COLUMN
        // TYPE only changes the column's type in place — it leaves any
        // existing CHECK constraint attached, so 'purchase' kept getting
        // rejected by the stale constraint. Drop it explicitly here.
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stock_adjustments DROP CONSTRAINT IF EXISTS stock_adjustments_reason_check');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE stock_adjustments ADD CONSTRAINT stock_adjustments_reason_check CHECK (reason IN ('purchase', 'waste', 'damage', 'theft', 'recount', 'other'))");
        }
    }
};
