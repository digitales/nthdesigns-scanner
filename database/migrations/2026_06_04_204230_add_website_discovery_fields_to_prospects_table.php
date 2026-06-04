<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->string('website_url_source', 20)->default('gbp')->after('website_url');
            $table->string('website_discovery_confidence', 10)->nullable()->after('website_url_source');
            $table->timestamp('website_discovered_at')->nullable()->after('website_discovery_confidence');
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropColumn([
                'website_url_source',
                'website_discovery_confidence',
                'website_discovered_at',
            ]);
        });
    }
};
