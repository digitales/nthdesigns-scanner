<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_cpc_defaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('niche', 100);
            $table->string('city', 100);
            $table->char('country', 2)->default('GB');
            $table->decimal('cpc_benchmark', 8, 2)->nullable();
            $table->string('cpc_source', 32)->nullable();
            $table->json('cpc_keywords')->nullable();
            $table->string('cpc_geo_target')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'niche', 'city', 'country']);
        });

        Schema::table('searches', function (Blueprint $table) {
            $table->json('cpc_keywords')->nullable()->after('cpc_source');
            $table->string('cpc_geo_target')->nullable()->after('cpc_keywords');
        });
    }

    public function down(): void
    {
        Schema::table('searches', function (Blueprint $table) {
            $table->dropColumn(['cpc_keywords', 'cpc_geo_target']);
        });

        Schema::dropIfExists('market_cpc_defaults');
    }
};
