<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('searches', function (Blueprint $table) {
            $table->string('source')->default('discovery')->after('user_id');
            $table->string('submitted_url')->nullable()->after('source');
            $table->string('niche')->nullable()->change();
            $table->string('city')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('searches', function (Blueprint $table) {
            $table->dropColumn(['source', 'submitted_url']);
            $table->string('niche')->nullable(false)->change();
            $table->string('city')->nullable(false)->change();
        });
    }
};
