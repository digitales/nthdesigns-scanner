<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('searches', function (Blueprint $table) {
            $table->decimal('cpc_benchmark', 8, 2)->nullable()->after('benchmark_snapshot');
            $table->string('cpc_source', 32)->nullable()->after('cpc_benchmark');
        });

        Schema::table('outreach_emails', function (Blueprint $table) {
            $table->decimal('cpc_benchmark', 8, 2)->nullable()->after('pitch_angle');
            $table->string('cpc_source', 32)->nullable()->after('cpc_benchmark');
        });
    }

    public function down(): void
    {
        Schema::table('searches', function (Blueprint $table) {
            $table->dropColumn(['cpc_benchmark', 'cpc_source']);
        });

        Schema::table('outreach_emails', function (Blueprint $table) {
            $table->dropColumn(['cpc_benchmark', 'cpc_source']);
        });
    }
};
