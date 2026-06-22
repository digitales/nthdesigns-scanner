<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REASONS = ['acquired', 'cold', 'outreach_failed', 'unsubscribed', 'reviewed', 'other'];

    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::table('ignored_prospects')->get();

            Schema::drop('ignored_prospects');

            Schema::create('ignored_prospects', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('place_id');
                $table->enum('reason', self::REASONS);
                $table->text('note')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'place_id']);
            });

            foreach ($rows as $row) {
                DB::table('ignored_prospects')->insert((array) $row);
            }

            return;
        }

        if ($driver === 'mysql') {
            $values = implode("','", self::REASONS);
            DB::statement("ALTER TABLE ignored_prospects MODIFY reason ENUM('{$values}') NOT NULL");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE ignored_prospects DROP CONSTRAINT IF EXISTS ignored_prospects_reason_check');
            $values = implode("', '", self::REASONS);
            DB::statement("ALTER TABLE ignored_prospects ADD CONSTRAINT ignored_prospects_reason_check CHECK (reason IN ('{$values}'))");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $legacyReasons = ['acquired', 'cold', 'outreach_failed', 'unsubscribed', 'other'];

        if ($driver === 'sqlite') {
            $rows = DB::table('ignored_prospects')
                ->where('reason', '!=', 'reviewed')
                ->get();

            Schema::drop('ignored_prospects');

            Schema::create('ignored_prospects', function (Blueprint $table) use ($legacyReasons) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('place_id');
                $table->enum('reason', $legacyReasons);
                $table->text('note')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'place_id']);
            });

            foreach ($rows as $row) {
                DB::table('ignored_prospects')->insert((array) $row);
            }

            return;
        }

        if ($driver === 'mysql') {
            DB::table('ignored_prospects')->where('reason', 'reviewed')->delete();
            $values = implode("','", $legacyReasons);
            DB::statement("ALTER TABLE ignored_prospects MODIFY reason ENUM('{$values}') NOT NULL");

            return;
        }

        if ($driver === 'pgsql') {
            DB::table('ignored_prospects')->where('reason', 'reviewed')->delete();
            DB::statement('ALTER TABLE ignored_prospects DROP CONSTRAINT IF EXISTS ignored_prospects_reason_check');
            $values = implode("', '", $legacyReasons);
            DB::statement("ALTER TABLE ignored_prospects ADD CONSTRAINT ignored_prospects_reason_check CHECK (reason IN ('{$values}'))");
        }
    }
};
