<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STATUSES = ['pending', 'warming', 'ready', 'at_risk', 'paused', 'failed'];

    public function up(): void
    {
        if (! Schema::hasColumn('warmup_mailboxes', 'consecutive_failures')) {
            Schema::table('warmup_mailboxes', function (Blueprint $table) {
                $table->unsignedSmallInteger('consecutive_failures')->default(0)->after('last_imap_check_at');
            });
        }

        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE warmup_mailboxes DROP CONSTRAINT IF EXISTS warmup_mailboxes_status_check');

        $allowed = implode(', ', array_map(fn (string $status) => "'{$status}'", self::STATUSES));

        DB::statement("ALTER TABLE warmup_mailboxes ADD CONSTRAINT warmup_mailboxes_status_check CHECK (status IN ({$allowed}))");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            if (Schema::hasColumn('warmup_mailboxes', 'consecutive_failures')) {
                Schema::table('warmup_mailboxes', function (Blueprint $table) {
                    $table->dropColumn('consecutive_failures');
                });
            }

            return;
        }

        DB::statement('ALTER TABLE warmup_mailboxes DROP CONSTRAINT IF EXISTS warmup_mailboxes_status_check');

        $allowed = implode(', ', array_map(
            fn (string $status) => "'{$status}'",
            array_values(array_filter(self::STATUSES, fn (string $status) => $status !== 'at_risk')),
        ));

        DB::statement("ALTER TABLE warmup_mailboxes ADD CONSTRAINT warmup_mailboxes_status_check CHECK (status IN ({$allowed}))");

        if (Schema::hasColumn('warmup_mailboxes', 'consecutive_failures')) {
            Schema::table('warmup_mailboxes', function (Blueprint $table) {
                $table->dropColumn('consecutive_failures');
            });
        }
    }
};
