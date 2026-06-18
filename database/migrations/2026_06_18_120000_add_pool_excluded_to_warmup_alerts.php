<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TYPES = ['connection_failed', 'ready', 'at_risk', 'pool_excluded'];

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE warmup_alerts DROP CONSTRAINT IF EXISTS warmup_alerts_type_check');

        $allowed = implode(', ', array_map(fn (string $type) => "'{$type}'", self::TYPES));

        DB::statement("ALTER TABLE warmup_alerts ADD CONSTRAINT warmup_alerts_type_check CHECK (type IN ({$allowed}))");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE warmup_alerts DROP CONSTRAINT IF EXISTS warmup_alerts_type_check');

        $allowed = implode(', ', array_map(
            fn (string $type) => "'{$type}'",
            array_values(array_filter(self::TYPES, fn (string $type) => $type !== 'pool_excluded')),
        ));

        DB::statement("ALTER TABLE warmup_alerts ADD CONSTRAINT warmup_alerts_type_check CHECK (type IN ({$allowed}))");
    }
};
