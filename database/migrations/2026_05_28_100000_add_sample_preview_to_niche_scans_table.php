<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('niche_scans', function (Blueprint $table) {
            $table->json('sample_preview')->nullable()->after('sampled_count');
        });
    }

    public function down(): void
    {
        Schema::table('niche_scans', function (Blueprint $table) {
            $table->dropColumn('sample_preview');
        });
    }
};
