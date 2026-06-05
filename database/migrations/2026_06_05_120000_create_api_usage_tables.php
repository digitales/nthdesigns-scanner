<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_usage_counters', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('operation', 32);
            $table->string('period_type', 16);
            $table->string('period_key', 16);
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['provider', 'operation', 'period_type', 'period_key'], 'api_usage_counters_unique');
        });

        Schema::create('api_quota_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('google_places_text_search_daily')->nullable();
            $table->unsignedInteger('google_places_text_search_monthly')->nullable();
            $table->unsignedInteger('google_places_place_details_daily')->nullable();
            $table->unsignedInteger('google_places_place_details_monthly')->nullable();
            $table->unsignedInteger('brave_web_search_daily')->nullable();
            $table->unsignedInteger('brave_web_search_monthly')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_quota_settings');
        Schema::dropIfExists('api_usage_counters');
    }
};
